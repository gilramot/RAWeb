<?php

// TODO migrate to UserController::show() pages/user.blade.php

use App\Community\Enums\ArticleType;
use App\Community\Enums\ClaimFilters;
use App\Community\Enums\ClaimSorting;
use App\Community\Enums\UserAction;
use App\Enums\Permissions;
use App\Models\User;
use App\Platform\Services\PlayerProgressionService;

$userPage = request('user');
if (empty($userPage) || !isValidUsername($userPage)) {
    abort(404);
}

authenticateFromCookie($user, $permissions, $userDetails);

$maxNumGamesToFetch = requestInputSanitized('g', 5, 'integer');

if ($maxNumGamesToFetch < 1 || $maxNumGamesToFetch > 100) {
    abort(400);
}

$userPageModel = User::firstWhere('User', $userPage);
if (!$userPageModel) {
    abort(404);
}

$userMassData = getUserPageInfo($userPage, numGames: $maxNumGamesToFetch);
if (empty($userMassData)) {
    abort(404);
}

if ((int) $userMassData['Permissions'] < Permissions::Unregistered && $permissions < Permissions::Moderator) {
    abort(404);
}

$userPage = $userMassData['User'];
$userMotto = $userMassData['Motto'];
$userPageID = $userMassData['ID'];
$userSetRequestInformation = getUserRequestsInformation($userPageModel);
$userWallActive = $userMassData['UserWallActive'];
$userIsUntracked = $userMassData['Untracked'];

// Get wall
$numArticleComments = getRecentArticleComments(ArticleType::User, $userPageID, $commentData);

// Get user's feed
// $numFeedItems = getFeed( $userPage, 20, 0, $feedData, 0, 'individual' );

// Calc avg pcts:
$totalPctWon = 0.0;
$numGamesFound = 0;

// Achievement totals
$totalHardcoreAchievements = 0;
$totalSoftcoreAchievements = 0;

$userCompletedGamesList = getUsersCompletedGamesAndMax($userPage);
$userAwards = getUsersSiteAwards($userPage);

$playerProgressionService = new PlayerProgressionService();
$userJoinedGamesAndAwards = $playerProgressionService->filterAndJoinGames(
    $userCompletedGamesList,
    $userAwards,
);

$excludedConsoles = ["Hubs", "Events"];

foreach ($userCompletedGamesList as $nextGame) {
    if ($nextGame['PctWon'] > 0) {
        if (!in_array($nextGame['ConsoleName'], $excludedConsoles)) {
            $totalPctWon += $nextGame['PctWon'];
            $numGamesFound++;
            $totalHardcoreAchievements += $nextGame['NumAwardedHC'];
            $totalSoftcoreAchievements += ($nextGame['NumAwarded'] - $nextGame['NumAwardedHC']);
        }
    }
}

$avgPctWon = "0.00";
if ($numGamesFound > 0) {
    $avgPctWon = sprintf("%01.2f", ($totalPctWon / $numGamesFound) * 100.0);
}

sanitize_outputs(
    $userMotto,
    $userPage,
    $userMassData['RichPresenceMsg']
);

$pageTitle = "$userPage";

$daysRecentProgressToShow = 14; // fortnight

$userScoreData = getAwardedList(
    $userPageModel,
    0,
    $daysRecentProgressToShow,
    date("Y-m-d H:i:s", time() - 60 * 60 * 24 * $daysRecentProgressToShow),
    date("Y-m-d H:i:s", time())
);

// Get claim data if the user has jr dev or above permissions
$userClaimData = null;
if (getActiveClaimCount($userPage, true, true) > 0) {
    // Active claims sorted by game title
    $userClaimData = getFilteredClaims(
        claimFilter: ClaimFilters::AllActiveClaims,
        sortType: ClaimSorting::GameAscending,
        username: $userPage
    );
}
?>
<x-app-layout
    :pageTitle="$userPage"
    :pageDescription="$userPage . ' Profile'"
    :pageImage="media_asset('/UserPic/' . $userPage . '.png')"
    pageType="retroachievements:user"
>
    <x-user-profile-meta
        :averageCompletionPercentage="$avgPctWon"
        :totalHardcoreAchievements="$totalHardcoreAchievements"
        :totalSoftcoreAchievements="$totalSoftcoreAchievements"
        :user="$userPageModel"
        :userJoinedGamesAndAwards="$userJoinedGamesAndAwards"
        :userMassData="$userMassData"
        :userClaims="$userClaimData?->toArray()"
    />
    <?php
    $canShowProgressionStatusComponent =
        !empty($userCompletedGamesList)
        // Needs at least one non-event game.
        && count(array_filter($userCompletedGamesList, fn ($game) => $game['ConsoleID'] != 101)) > 0;

    if ($canShowProgressionStatusComponent) {
        echo "<hr class='border-neutral-700 black:border-embed-highlight light:border-embed-highlight my-4' />";

        echo "<div class='mt-1 mb-8 bg-embed p-5 rounded'>";
        ?>
        <x-user-progression-status
            :userCompletionProgress="$userCompletedGamesList"
            :userJoinedGamesAndAwards="$userJoinedGamesAndAwards"
            :userSiteAwards="$userAwards"
            :userRecentlyPlayed="$userMassData['RecentlyPlayed']"
            :userHardcorePoints="$userMassData['TotalPoints']"
            :userSoftcorePoints="$userMassData['TotalSoftcorePoints']"
        />
        <?php
        echo "</div>";
    }

    if (isset($user) && $permissions >= Permissions::Moderator) {
        echo "<div class='devbox mt-0'>";
        echo "<span onclick=\"$('#devboxcontent').toggle(); return false;\">Admin ▼</span>";
        echo "<div id='devboxcontent' class='bg-embed' style='display: none'>";

        echo "<table>";

        if ($permissions >= $userMassData['Permissions'] && ($user != $userPage)) {
            echo "<tr>";
            echo "<form method='post' action='/request/user/update.php'>";
            echo csrf_field();
            echo "<input type='hidden' name='property' value='" . UserAction::UpdatePermissions . "' />";
            echo "<input type='hidden' name='target' value='$userPage' />";
            echo "<td class='text-right'>";
            echo "<button class='btn'>Update Account Type</button>";
            echo "</td><td>";
            echo "<select name='value' >";
            $i = Permissions::Banned;
            // Don't do this, looks weird when trying to change someone above you
            // while( $i <= $permissions && ( $i <= Permissions::Developer || $user == 'Scott' ) )
            while ($i <= $permissions) {
                if ($userMassData['Permissions'] == $i) {
                    echo "<option value='$i' selected >($i): " . Permissions::toString($i) . " (current)</option>";
                } else {
                    echo "<option value='$i'>($i): " . Permissions::toString($i) . "</option>";
                }
                $i++;
            }
            echo "</select>";

            echo "</td></form></tr>";
        }

        echo "<tr><td class='text-right'>";
        echo "<form method='post' action='/request/user/update.php'>";
        echo csrf_field();
        echo "<input type='hidden' name='property' value='" . UserAction::PatreonBadge . "' />";
        echo "<input type='hidden' name='target' value='$userPage' />";
        echo "<input type='hidden' name='value' value='0' />";
        echo "<button class='btn'>Toggle Patreon Supporter</button>";
        echo "</form>";
        echo "</td><td>";
        echo HasPatreonBadge($userPage) ? "Patreon Supporter" : "Not a Patreon Supporter";
        echo "</td></tr>";

        echo "<tr><td class='text-right'>";
        echo "<form method='post' action='/request/user/update.php'>";
        echo csrf_field();
        echo "<input type='hidden' name='property' value='" . UserAction::LegendBadge . "' />";
        echo "<input type='hidden' name='target' value='$userPage' />";
        echo "<input type='hidden' name='value' value='0' />";
        echo "<button class='btn'>Toggle Certified Legend</button>";
        echo "</form>";
        echo "</td><td>";
        echo HasCertifiedLegendBadge($userPage) ? "Certified Legend" : "Not Yet Legendary";
        echo "</td></tr>";

        $newValue = $userIsUntracked ? 0 : 1;
        echo "<tr><td class='text-right'>";
        echo "<form method='post' action='/request/user/update.php'>";
        echo csrf_field();
        echo "<input type='hidden' name='property' value='" . UserAction::TrackedStatus . "' />";
        echo "<input type='hidden' name='target' value='$userPage' />";
        echo "<input type='hidden' name='value' value='$newValue' />";
        echo "<button class='btn btn-danger'>Toggle Tracked Status</button>";
        echo "</form>";
        echo "</td><td style='width: 100%'>";
        echo ($userIsUntracked == 1) ? "Untracked User" : "Tracked User";
        echo "</td></tr>";

        echo "<tr><td class='text-right'>";
        echo "<form method='post' action='/request/user/remove-avatar.php' onsubmit='return confirm(\"Are you sure you want to permanently delete this avatar?\")'>";
        echo csrf_field();
        echo "<input type='hidden' name='user' value='$userPage' />";
        echo "<button class='btn btn-danger'>Remove Avatar</button>";
        echo "</form>";
        echo "</td></tr>";

        echo "<tr><td colspan=2>";
        echo "<div class='commentscomponent left'>";
        $numLogs = getRecentArticleComments(ArticleType::UserModeration, $userPageID, $logs);
        RenderCommentsComponent($user,
            $numLogs,
            $logs,
            $userPageID,
            ArticleType::UserModeration,
            $permissions
        );
        echo "</div>";
        echo "</td></tr>";

        echo "</table>";

        echo "</div>"; // devboxcontent

        echo "</div>"; // devbox
    }

    echo "<div class='my-8'>";
    ?>
        <x-user-recently-played
            :recentlyPlayedCount="$userMassData['RecentlyPlayedCount'] ?? 0"
            :recentlyPlayedEntities="$userMassData['RecentlyPlayed'] ?? []"
            :recentAchievementEntities="$userMassData['RecentAchievements'] ?? []"
            :recentAwardedEntities="$userMassData['Awarded'] ?? []"
            :targetUsername="$user ?? ''"
            :userAwards="$userAwards"
        />
    <?php
    $recentlyPlayedCount = $userMassData['RecentlyPlayedCount'];
    if ($maxNumGamesToFetch == 5 && $recentlyPlayedCount == 5) {
        echo "<div class='text-right'><a class='btn btn-link' href='/user/$userPage?g=15'>more...</a></div>";
    }
    echo "</div>";

    echo "<div class='commentscomponent left mt-8'>";

    echo "<h2 class='text-h4'>User Wall</h2>";

    if ($userWallActive) {
        // passing 'null' for $user disables the ability to add comments
        RenderCommentsComponent(
            !isUserBlocking($userPage, $user) ? $user : null,
            $numArticleComments,
            $commentData,
            $userPageID,
            ArticleType::User,
            $permissions
        );
    } else {
        echo "<div>";
        echo "<i>This user has disabled comments.</i>";
        echo "</div>";
    }

    echo "</div>";
    ?>
    <x-slot name="sidebar">
        <?php
        $prefersHiddenUserCompletedSets = request()->cookie('prefers_hidden_user_completed_sets') === 'true';

        RenderSiteAwards($userAwards, $userPage);
        ?>

        @if (count($userCompletedGamesList) >= 1)
            <x-user.completion-progress
                :userJoinedGamesAndAwards="$userJoinedGamesAndAwards"
                :username="$userPage"
            />
        @endif

        <x-user.recent-progress
            :hasAnyPoints="$userMassData['TotalPoints'] > 0 || $userMassData['TotalSoftcorePoints'] > 0"
            :username="$userPage"
            :userScoreData="$userScoreData"
        />

        @if ($user !== null && $user === $userPage)
            <x-user.followed-leaderboard-cta :friendCount="getFriendCount($user)" />
        @endif
    </x-slot>
</x-app-layout>
