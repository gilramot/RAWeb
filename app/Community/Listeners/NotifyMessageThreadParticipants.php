<?php

declare(strict_types=1);

namespace App\Community\Listeners;

use App\Community\Actions\UpdateUnreadMessageCountAction;
use App\Community\Events\MessageCreated;
use App\Enums\UserPreference;
use App\Mail\PrivateMessageReceivedMail;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\User;
use App\Support\Shortcode\Shortcode;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class NotifyMessageThreadParticipants
{
    public function handle(MessageCreated $event): void
    {
        $message = $event->message;

        $userFrom = User::firstWhere('ID', $message->author_id);
        if (!$userFrom) {
            return;
        }

        $thread = MessageThread::firstWhere('id', $message->thread_id);
        if (!$thread) {
            return;
        }

        $updateUnreadMessageCountAction = new UpdateUnreadMessageCountAction();

        $participants = MessageThreadParticipant::withTrashed()->where('thread_id', $message->thread_id)->get();
        foreach ($participants as $participant) {
            if ($participant->user_id == $message->author_id) {
                // don't notify the sender
                continue;
            }

            $userTo = User::firstWhere('ID', $participant->user_id);
            if (!$userTo) {
                // ignore deleted users
                continue;
            }

            if ($userTo->isBlocking($userFrom)) {
                // ignore users who have blocked the sender
                continue;
            }

            // use direct update to avoid race condition
            DB::statement("UPDATE message_thread_participants
                           SET num_unread = num_unread + 1, deleted_at = NULL
                           WHERE id = {$participant->id}");

            $updateUnreadMessageCountAction->execute($userTo);

            // send email?
            if (BitSet($userTo->websitePrefs, UserPreference::EmailOn_PrivateMessage)) {
                if (!$userTo->is($userFrom)) {
                    Mail::to($userTo)->queue(new PrivateMessageReceivedMail(
                        $userTo,
                        $userFrom,
                        $thread,
                        $message,
                    ));
                }
            }

            $this->forwardToDiscord($userFrom, $userTo, $thread, $message);
        }
    }

    private function forwardToDiscord(
        User $userFrom,
        User $userTo,
        MessageThread $messageThread,
        Message $message
    ): void {
        $message->body = Shortcode::stripAndClamp($message->body, 1850, preserveWhitespace: true);

        $inboxConfig = config('services.discord.inbox_webhook.' . $userTo->username);

        // If no config exists for this user or no URL is configured, bail early.
        if ($inboxConfig === null || empty($inboxConfig['url'] ?? null)) {
            return;
        }

        // Default webhook URL.
        $webhookUrl = $inboxConfig['url'];

        if (empty($messageThread->title) || empty($message->body)) {
            return;
        }

        // Set default values.
        $color = hexdec('0x0066CC');
        $isForum = $inboxConfig['is_forum'] ?? false;

        // Process special message types only if the corresponding config keys exist.
        $messageTitle = mb_strtolower($messageThread->title);

        // Discord user verification messages.
        if (isset($inboxConfig['verify_url']) && (
            mb_strpos($messageTitle, 'verify') !== false
            || mb_strpos($messageTitle, 'verified') !== false
            || mb_strpos($messageTitle, 'verifying') !== false
            || mb_strpos($messageTitle, 'verification') !== false
            || mb_strpos($messageTitle, 'discord') !== false
        )) {
            $webhookUrl = $inboxConfig['verify_url'];
            $color = hexdec('0x00CC66');
            $isForum = false;
        }

        // Deletion messages - just change the color, don't change the URL.
        if (
            mb_strpos($messageTitle, 'delete') !== false
            || mb_strpos($messageTitle, 'deleting') !== false
            || mb_strpos($messageTitle, 'deletion') !== false
        ) {
            $color = hexdec('0xCC6600');
        }

        // Manual unlock messages.
        if (isset($inboxConfig['manual_unlock_url']) && mb_strpos($messageTitle, 'manual') !== false) {
            $webhookUrl = $inboxConfig['manual_unlock_url'];
            $color = hexdec('0xCC0066');
            $isForum = false;
        }

        // If true, has title like "Kind: Achievement Name [Achievement ID] (Game Name)"
        $structuredTitlePrefixes = [
            'Incorrect type:' => 'incorrect_type_url',
            'Issue:' => 'achievement_issues_url',
            'Unwelcome Concept:' => 'unwelcome_concept_url',
            'Writing:' => 'url',
        ];

        foreach ($structuredTitlePrefixes as $prefix => $configKey) {
            if (mb_strpos($messageThread->title, $prefix) !== false && isset($inboxConfig[$configKey])) {
                $webhookUrl = $inboxConfig[$configKey];
                $isForum = true;

                // Extract the achievement ID from the message thread title.
                // We'll auto-insert a link to the achievement at the top of the message.
                if (preg_match('/\[([0-9]+)\]/', $messageThread->title, $matches)) {
                    $achievementId = $matches[1];
                    $achievementUrl = route('achievement.show', $achievementId);
                    $message->body = $achievementUrl . "\n\n" . $message->body;

                    // We want to reformat the incoming structured title before it lands in the team forum.
                    //  - Original:  "Unwelcome Concept: Lots of Rings [12345] (Sonic the Hedgehog)"
                    //  - Formatted: "12345: Lots of Rings (Sonic the Hedgehog)"
                    if (preg_match(
                            '/^(Incorrect type:|Issue:|Unwelcome Concept:|Writing:)\s*(.*)\s*\[([0-9]+)\]\s*(\(.*\))$/',
                            $messageThread->title,
                            $titleMatches
                        )
                    ) {
                        $newTitle = $achievementId . ': ' . $titleMatches[2] . ' ' . $titleMatches[4];
                        $messageThread->title = $newTitle;
                    }
                }

                break;
            }
        }

        $payload = [
            'username' => $userTo->username . ' Inbox',
            'avatar_url' => $userTo->avatar_url,
            'embeds' => [
                [
                    'author' => [
                        'name' => $userFrom->display_name,
                        // TODO 'url' => route('user.show', $userFrom),
                        'url' => url('user/' . $userFrom->display_name),
                        'icon_url' => $userFrom->avatar_url,
                    ],
                    'title' => mb_substr($messageThread->title, 0, 100),
                    'url' => route('message-thread.show', ['messageThread' => $messageThread->id]),
                    'description' => mb_substr($message->body, 0, 2000),
                    'color' => $color,
                ],
            ],
        ];

        if ($isForum) {
            // Forum channels require an additional 'thread_name' JSON parameter to be successfully posted.
            $payload['thread_name'] = mb_substr($messageThread->title, 0, 100);
        }

        (new Client())->post($webhookUrl, ['json' => $payload]);
    }
}
