<?php

declare(strict_types=1);

return [

    // ── Navigation ────────────────────────────────────────────────
    'nav' => [
        'login'   => 'Log in',
        'logout'  => 'Log out',
        'admin'   => 'Admin',
        'profile' => 'Profile',
    ],

    // ── Footer ────────────────────────────────────────────────────
    'footer' => [
        'powered_by' => 'Powered by',
    ],

    // ── Auth ──────────────────────────────────────────────────────
    'auth' => [
        'login' => [
            'title'       => 'Sign in',
            'username'    => 'Username',
            'password'    => 'Password',
            'submit'      => 'Sign in',
            'invalid'           => 'Invalid username or password.',
            'required'          => 'Username and password are required.',
            'too_many_attempts' => 'Too many failed attempts. Please try again in 15 minutes.',
        ],
        'logout' => [
            'success' => 'You have been logged out.',
        ],
        'magic_link' => [
            'identifier'        => 'Email or username',
            'continue'          => 'Continue',
            'sent'              => 'A sign-in link has been sent to your email address.',
            'sent_detail'       => 'Click the link in the email to sign in instantly.',
            'use_password'      => 'Use my password instead',
            'change_identifier' => 'Change',
            'expired'           => 'This sign-in link has expired or has already been used.',
        ],
        'first_login' => [
            'title'   => 'Choose a new password',
            'intro'   => 'Your password is temporary. Please choose a new password to continue.',
            'new'     => 'New password',
            'confirm' => 'Confirm new password',
            'submit'  => 'Set new password',
        ],
        'forgot_password' => [
            'link'   => 'Forgot your password?',
            'title'  => 'Password reset',
            'intro'  => 'Enter the email address associated with your account. If a matching account exists, you will receive a temporary password.',
            'email'  => 'Email address',
            'submit' => 'Send temporary password',
            'sent'   => 'If an account exists with this email address, you will receive a temporary password shortly.',
            'back'   => 'Back to login',
        ],
    ],

    // ── Setup wizard ─────────────────────────────────────────────
    'setup' => [
        'title'            => 'First-run setup',
        'step1'            => 'Create administrator account',
        'step2'            => 'Jellyfin configuration',
        'username'         => 'Admin username',
        'password'         => 'Admin password',
        'email'            => 'Admin email address',
        'jellyfin_url'     => 'Jellyfin server URL',
        'jellyfin_api_key'      => 'Jellyfin API key',
        'jellyfin_api_key_hint' => 'Jellyfin → Administration → Dashboard → API keys',
        'site_title'            => 'Site title',
        'submit'           => 'Create and continue',
        'success'          => 'Setup complete. Welcome!',
        'already_done'     => 'Setup has already been completed.',
        'validation' => [
            'username_required'     => 'Username is required.',
            'password_required'     => 'Password is required.',
            'password_min'          => 'Password must be at least 8 characters.',
            'email_required'        => 'Email address is required.',
            'email_invalid'         => 'Please enter a valid email address.',
            'jellyfin_url_required' => 'Jellyfin URL is required.',
            'api_key_required'      => 'API key is required.',
        ],
        'requirements' => [
            'title'       => 'System requirements',
            'all_ok'      => 'All requirements are met.',
            'some_fail'   => 'Some requirements are not met. Please fix them before continuing.',
            'php'         => 'PHP version ≥ 8.2',
            'pdo_sqlite'  => 'Extension pdo_sqlite',
            'mbstring'    => 'Extension mbstring',
            'openssl'     => 'Extension openssl',
            'data_dir'    => 'data/ directory writable',
            'cache_dir'   => 'cache/ directory writable',
            'posters_dir' => 'cache/posters/ directory writable',
        ],
    ],

    // ── Library ──────────────────────────────────────────────────
    'library' => [
        'title'         => 'Library',
        'all'           => 'All',
        'movies'        => 'Movies',
        'shows'         => 'TV Shows',
        'movie'         => 'Movie',
        'show'          => 'TV Show',
        'search'        => 'Search…',
        'sort'          => [
            'label'   => 'Sort by',
            'title'   => 'Title A→Z',
            'year'    => 'Year',
            'added'   => 'Recently added',
            'rating'  => 'Rating',
        ],
        'no_results'    => 'No results found.',
        'empty'         => 'The library is empty.',
        'pagination' => [
            'previous' => 'Previous',
            'next'     => 'Next',
            'of'       => 'of',
            'items'    => 'items',
        ],
    ],

    // ── Movie detail ─────────────────────────────────────────────
    'movie' => [
        'download'   => 'Download',
        'year'       => 'Year',
        'genres'     => 'Genres',
        'rating'     => 'Rating',
        'duration'   => 'Duration',
        'synopsis'   => 'Synopsis',
        'no_synopsis'=> 'No synopsis available.',
    ],

    // ── TV show detail ────────────────────────────────────────────
    'show' => [
        'seasons'        => 'seasons',
        'episodes'       => 'episodes',
        'season'         => 'Season :number',
        'episode'        => 'Episode :number',
        'download'       => 'Download',
        'no_episodes'    => 'No episodes available.',
        'no_seasons'     => 'No seasons available.',
    ],

    // ── Admin ─────────────────────────────────────────────────────
    'admin' => [
        'title'    => 'Admin panel',
        'nav' => [
            'dashboard' => 'Dashboard',
            'settings'  => 'Settings',
            'users'     => 'Users',
            'logs'      => 'Download logs',
            'sessions'  => 'Sessions',
        ],
        'dashboard' => [
            'jellyfin_connected'   => 'Connected',
            'jellyfin_unreachable' => 'Unreachable',
        ],
        'settings' => [
            'title'              => 'Settings',
            'jellyfin'           => 'Jellyfin',
            'jellyfin_url'       => 'Jellyfin server URL',
            'jellyfin_api_key'         => 'API key',
            'api_key_replace'          => 'Replace',
            'api_key_cancel'           => 'Cancel',
            'api_key_new_placeholder'  => 'Paste new API key',
            'site'               => 'Site',
            'site_title'         => 'Site title',
            'public_mode'        => 'Public mode (no login required)',
            'grid_rows'          => 'Rows per page',
            'timezone'           => 'Timezone',
            'poster_cache_days'   => 'Poster cache duration (days)',
            'session_max_days'    => 'Maximum session duration (days)',
            'session_max_days_hint' => '0 = no limit',
            'locale'             => 'Default language',
            'default_sort'       => 'Default sort order',
            'smtp'               => 'Email (SMTP)',
            'smtp_host'          => 'SMTP host',
            'smtp_port'          => 'SMTP port',
            'smtp_user'          => 'SMTP username',
            'smtp_password'      => 'SMTP password',
            'smtp_from'              => 'From address',

            'test_email' => [
                'title'  => 'Send a test email',
                'to'     => 'Recipient address',
                'send'   => 'Send test',
                'notice' => 'Save your SMTP settings before sending a test.',
            ],
            'allow_password_reset'      => 'Allow users to reset their own password by email',
            'allow_password_reset_hint' => 'Requires SMTP to be configured.',
            'allow_magic_link'          => 'Allow sign-in by magic link (passwordless email)',
            'allow_magic_link_hint'       => 'Requires SMTP to be configured.',
            'allow_magic_link_hint_users' => 'Only works for users with a registered email address.',
            'discord'                       => 'Discord',
            'discord_webhook_url'           => 'Webhook URL',
            'discord_webhook_url_placeholder' => 'https://discord.com/api/webhooks/…',
            'discord_notify_downloads'      => 'Post a message on each download',
            'discord_test_title'            => 'Send a test message',
            'discord_test_notice'           => 'Save your webhook URL before sending a test.',
            'discord_test_send'             => 'Send test',
            'discord_test_sent'             => 'Test message sent to Discord.',
            'save'               => 'Save settings',
            'saved'              => 'Settings saved.',
        ],
        'users' => [
            'title'            => 'Users',
            'create'           => 'Create user',
            'username'         => 'Username',
            'email'            => 'Email address',
            'role'             => 'Role',
            'access'           => 'Content access',
            'last_activity'    => 'Last activity',
            'actions'          => 'Actions',
            'delete'           => 'Delete',
            'delete_confirm'   => 'Are you sure you want to delete this user?',
            'edit'             => 'Edit',
            'edit_title'       => 'Edit user —',
            'you'              => 'you',
            'save'             => 'Save changes',
            'saved'            => 'Saved!',
            'section_settings' => 'Settings',
            'roles' => [
                'admin' => 'Admin',
                'user'  => 'User',
            ],
            'access_types' => [
                'movies' => 'Movies only',
                'shows'  => 'Shows only',
                'both'   => 'Movies & shows',
            ],
            'username_taken'   => 'This username is already taken.',
            'self_role_hint'   => 'You cannot change your own role.',
            'send_invitation'        => 'Send invitation email with credentials',
            'force_password_change'  => 'Force password change on next login',
            'generate_password'       => 'Generate',
            'cancel'                => 'Cancel',
            'close'           => 'Close',
            'created'  => 'User created.',
            'deleted'  => 'User deleted.',
            'updated'  => 'User updated.',
            'reset_password' => [
                'button'     => 'Reset password',
                'self_error' => 'Use your profile page to change your own password.',
                'done'       => 'Password reset.',
                'send_email' => 'Send by email',
                'email_sent' => 'Email sent to the user.',
                'no_email'   => 'This user has no email address configured.',
                'no_smtp'    => 'SMTP is not configured — email cannot be sent.',
            ],
        ],
        'logs' => [
            'title'         => 'Download logs',
            'user'          => 'User',
            'item'          => 'Item',
            'type'          => 'Type',
            'date'          => 'Date',
            'type_movie'    => 'Movie',
            'type_episode'  => 'Episode',
            'clear'         => 'Clear all logs',
            'clear_confirm' => 'Are you sure you want to clear all download logs?',
            'cleared'       => 'Logs cleared.',
            'empty'         => 'No download logs yet.',
        ],
        'sessions' => [
            'revoke'         => 'Revoke all sessions',
            'revoke_confirm' => 'This will log out all users (your session will be preserved). Continue?',
            'revoked'        => 'All sessions have been revoked.',
        ],
    ],

    // ── Errors ────────────────────────────────────────────────────
    'error' => [
        'not_found'          => 'Page not found',
        'not_found_detail'   => 'The page you are looking for does not exist.',
        'server_error'       => 'Server error',
        'server_error_detail'=> 'Something went wrong on our end. Please try again later.',
        'back_home'          => 'Back to home',
        'forbidden'          => 'Access denied',
        'forbidden_detail'   => 'You do not have permission to access this page.',
    ],

    // ── Profile / My account ─────────────────────────────────────
    'profile' => [
        'title' => 'My account',
        'password' => [
            'title'         => 'Change password',
            'current'       => 'Current password',
            'new'           => 'New password',
            'confirm'       => 'Confirm new password',
            'save'          => 'Update password',
            'success'       => 'Password updated.',
            'wrong_current' => 'Current password is incorrect.',
            'mismatch'      => 'Passwords do not match.',
            'too_short'     => 'Password must be at least 8 characters.',
        ],
        'email' => [
            'title'          => 'Email address',
            'current'        => 'Current address',
            'placeholder'    => 'new@example.com',
            'save'           => 'Update',
            'updated'        => 'Email address updated.',
            'already_in_use' => 'This email address is already in use.',
        ],
        'sessions' => [
            'title'          => 'Active sessions',
            'current'        => 'Current session',
            'revoke'         => 'End session',
            'revoke_others'  => 'End all other sessions',
            'revoked'        => 'Session ended.',
            'revoked_others' => 'All other sessions have been ended.',
        ],
    ],

    // ── Emails ───────────────────────────────────────────────────
    'email' => [
        'footer'         => ':site — this is an automated message, please do not reply.',
        'greeting'       => 'Hi :username,',
        'signin_btn'     => 'Sign in to :site',
        'label_url'      => 'URL',
        'label_username' => 'Username',
        'label_password' => 'Password',
        'label_link'     => 'Link',

        'invitation' => [
            'subject'           => 'Your :site account',
            'intro'             => 'An account has been created for you on <strong>:site</strong>. You can now log in and access the media library.',
            'credentials_intro' => 'Your credentials:',
        ],

        'password_reset' => [
            'subject'           => 'Your :site password has been reset',
            'subject_self'      => 'Your :site temporary password',
            'intro_admin'       => 'An administrator has reset your password on <strong>:site</strong>. You can log in with the temporary password below.',
            'intro_self'        => 'You requested a password reset on <strong>:site</strong>. Log in with the temporary password below.',
            'credentials_intro' => 'Your new credentials:',
        ],

        'magic_link' => [
            'subject' => 'Your sign-in link for :site',
            'intro'   => 'Click the button below to sign in to <strong>:site</strong> instantly. No password needed.',
            'expires' => 'This link expires in <strong>15 minutes</strong> and can only be used once.',
            'ignore'  => 'If you did not request this link, you can safely ignore this email.',
        ],

        'test' => [
            'subject' => 'Test email from :site',
            'intro'   => 'This is a test email sent from <strong>:site</strong>.',
            'success' => 'If you received this message, your SMTP configuration is working correctly.',
        ],
    ],

    // ── Discord notifications ────────────────────────────────────
    'discord' => [
        'download_message' => '**:user** downloaded **:title** (:type)',
        'anonymous'        => 'Anonymous',
        'type_movie'       => 'movie',
        'type_episode'     => 'episode',
    ],

    // ── Download ──────────────────────────────────────────────────
    'download' => [
        'starting'  => 'Your download is starting…',
        'forbidden' => 'You do not have permission to download this item.',
        'not_found' => 'This item could not be found.',
        'logged'    => 'Download logged.',
    ],

];
