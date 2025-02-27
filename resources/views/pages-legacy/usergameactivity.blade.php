<?php

// TODO migrate to PlayerGameController::activity() pages/user/game/activity.blade.php

use App\Enums\Permissions;
use App\Platform\Enums\AchievementFlag;

if (!authenticateFromCookie($user, $permissions, $userDetails, Permissions::Moderator)) {
    abort(401);
}

$gameID = requestInputSanitized('ID', 0, 'integer');
$user2 = requestInputSanitized('f');

if (empty($user2) || $gameID <= 0) {
    abort(404);
}

$gameData = getGameData($gameID);
$gameTitle = $gameData['Title'];
$consoleID = $gameData['ConsoleID'];
$consoleName = $gameData['ConsoleName'];

$activity = getUserGameActivity($user2, $gameID);
if (empty($activity)) {
    abort(404);
}

$estimated = ($activity['PerSessionAdjustment'] !== 0) ? " (estimated)" : "";

$unlockSessionCount = $activity['UnlockSessionCount'];
$sessionInfo = "$unlockSessionCount session";
if ($unlockSessionCount != 1) {
    $sessionInfo .= 's';

    if ($unlockSessionCount > 1) {
        $elapsedAchievementDays = ceil($activity['TotalUnlockTime'] / (24 * 60 * 60));
        if ($elapsedAchievementDays > 2) {
            $sessionInfo .= " over $elapsedAchievementDays days";
        } else {
            $sessionInfo .= " over " . ceil($activity['TotalUnlockTime'] / (60 * 60)) . " hours";
        }
    }
}

$gameAchievementCount = $activity['CoreAchievementCount'] ?? 0;
$userProgress = ($gameAchievementCount > 0) ? sprintf("/%d (%01.2f%%)",
    $gameAchievementCount, $activity['AchievementsUnlocked'] * 100 / $gameAchievementCount) : "n/a";
?>
<x-app-layout pageTitle="{{ $user2 }}'s activity for {{ $gameTitle }}">
    <?php
    echo "<div class='navpath'>";
    echo renderGameBreadcrumb($gameData);
    echo " &raquo; <b>$user2</b>";
    echo "</div>";

    echo "<h3>$gameTitle</h3>";

    $pageTitleAttr = attributeEscape($gameTitle);
    $imageIcon = media_asset($gameData['ImageIcon']);

    echo "<div class='sm:flex justify-between items-start gap-3 mb-3'>";
    echo "<img class='aspect-1 object-cover' src='$imageIcon' width='96' height='96' alt='$pageTitleAttr'>";
    echo "<table class='table-highlight'><colgroup><col class='w-48'></colgroup><tbody>";
    echo "<tr><td>User:</td><td>" . userAvatar($user2, icon: false) . "</td></tr>";
    if ($activity['TotalTime'] != $activity['AchievementsTime']) {
        echo "<tr><td>Total Playtime:</td><td>" . formatHMS($activity['TotalTime']) . "$estimated</td></tr>";
    }
    echo "<tr><td>Achievement Playtime:</td><td>" . formatHMS($activity['AchievementsTime']) . "$estimated</td></tr>";
    echo "<tr><td>Achievement Sessions:</td><td>$sessionInfo</td></tr>";
    echo "<tr><td>Achievements Unlocked:</td><td>" . $activity['AchievementsUnlocked'] . "$userProgress</td></tr>";
    echo "</tbody></table>";
    echo "</div>";

    echo "<div id='activity'>";
    echo "<table class='table-highlight'>";
    echo "<tr class='do-not-highlight'><th style='width: 20'></th><th style='width: 250'></th><th></th></tr>";

    foreach ($activity['Sessions'] as $session) {
        $startDate = getNiceDate($session['StartTime']);
        if ($session['IsGenerated'] ?? false) {
            echo "<tr><td colspan=2>$startDate</td><td>Generated Session</td></tr>";
        } else {
            echo "<tr><td colspan=2>$startDate</td><td>Started Playing</td></tr>";
        }

        $prevWhen = $session['StartTime'];
        foreach ($session['Achievements'] as $achievement) {
            $when = getNiceDate($achievement['When']);
            $formatted = formatHMS($achievement['When'] - $prevWhen);
            $prevWhen = $achievement['When'];

            echo "<tr><td>&nbsp;</td><td>$when<span class='smalltext text-muted'> (+$formatted)</span></td><td>";
            echo achievementAvatar($achievement);

            if ($achievement['Flags'] != AchievementFlag::OfficialCore) {
                echo " (Unofficial)";
            }

            if ($achievement['UnlockedLater'] ?? false) {
                echo " (unlocked again later)";
            }

            echo "</td></tr>";
        }

        if (array_key_exists('RichPresence', $session) && !empty($session['RichPresence'])) {
            $when = getNiceDate($session['RichPresenceTime']);
            $formatted = formatHMS($session['RichPresenceTime'] - $prevWhen);
            echo "<tr><td>&nbsp;</td><td>$when<span class='smalltext text-muted'> (+$formatted)</span></td><td>Rich Presence: {$session['RichPresence']}</td></tr>";
            $prevWhen = $session['RichPresenceTime'];
        }

        if ($session['EndTime'] != $prevWhen) {
            $when = getNiceDate($session['EndTime']);
            $formatted = formatHMS($session['EndTime'] - $prevWhen);
            echo "<tr><td>&nbsp;</td><td>$when<span class='smalltext text-muted'> (+$formatted)</span></td><td>End of session</td></tr>";
        }
    }

    echo "</table></div>";
    ?>
</x-app-layout>
