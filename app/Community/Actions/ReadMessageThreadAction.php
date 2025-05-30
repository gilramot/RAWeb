<?php

declare(strict_types=1);

namespace App\Community\Actions;

use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\User;

class ReadMessageThreadAction
{
    public function execute(MessageThread $thread, User $user): void
    {
        $participant = MessageThreadParticipant::where('user_id', $user->id)
            ->where('thread_id', $thread->id)
            ->whereNull('deleted_at')
            ->first();

        if ($participant) {
            ReadMessageThreadAction::markParticipantRead($participant, $user);
        }
    }

    // TODO actions should only publicly expose `execute()`.
    // this probably should be a new action.
    public static function markParticipantRead(MessageThreadParticipant $participant, User $user): void
    {
        if ($participant->num_unread) {
            $participant->num_unread = 0;
            $participant->save();

            (new UpdateUnreadMessageCountAction())->execute($user);
        }
    }
}
