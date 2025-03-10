<?php

return [
    'email_check' => __("Please check your email for further instructions."),
    'email_validate' => __("An email has been sent to the email address you supplied. Please click the link in that email."),

    'error' => [
        'error' => __("An error occurred. Please check and try again."),

        'account' => __("There appears to be a problem with your account. Please contact <a href='" . route('user.show', 'RAdmin') . "'>RAdmin</a> for more details."),
        'credentials' => __("Incorrect credentials."),
        'image_upload' => "Image could not be uploaded.",
        'game_modify' => __("Problems encountered while performing modification. Does the target game already exist? If so, try a merge instead on the target game title."),
        'game_merge' => __("Problems encountered while performing merge."),
        'permissions' => __('Insufficient permissions.'),
        'subscription_update' => __("Failed to update topic subscription."),
        'ticket_create' => __("There was an issue submitting your ticket."),
        'ticket_exists' => __("You already have a ticket for that achievement."),
        'token' => __('Invalid token.'),
        'recaptcha' => __("Invalid ReCaptcha."),
    ],

    'success' => [
        'create' => __("Created."),
        'delete' => __("Deleted."),
        'change' => __("Changed."),
        'modify' => __("Modified."),
        'ok' => __("OK."),
        'reset' => __("Reset."),
        'send' => __("Sent."),
        'submit' => __("Submitted."),
        'update' => __("Updated."),

        'achievement_unlocked' => __("Achievement unlocked."),
        'achievement_update' => __("Achievement updated."),
        'email_change' => __('Email address changed.'),
        'email_verify' => __("Email verified."),
        'game_merge' => __("Game merged."),
        'game_modify' => __("Game modified."),
        'image_upload' => "Image uploaded.",
        'message_delete' => __("Message deleted."),
        'message_send' => __("Message sent."),
        'password_change' => __('Password changed.'),
        'points_recalculate' => __("Score recalculated."),
        'subscribe' => __("Subscribed."),
        'unsubscribe' => __("Unsubscribed."),
        'ticket_create' => __("Your issue ticket has been successfully submitted."),
        'user_follow' => __("Following user."),
        'user_unfollow' => __("Unfollowed user."),
        'user_block' => __("User blocked."),
        'user_unblock' => __("User unblocked."),
    ],
];
