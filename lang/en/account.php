<?php

return [
    'registration' => [
        'title' => 'Create your account',
        'description' => 'You will then create your profile, and a tutorial will show you how to discover other members.',
        'email' => 'Email address',
        'email_placeholder' => 'you@example.com',
        'birth_date' => 'Date of birth',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'submit' => 'Create my account',
        'existing_account' => 'Already have an account?',
        'login' => 'Log in',
    ],
    'fields' => [
        'email' => 'Email address', 'email_placeholder' => 'you@example.com', 'password' => 'Password',
        'current_password' => 'Current password', 'new_password' => 'New password', 'password_confirmation' => 'Confirm password',
    ],
    'login' => [
        'page_title' => 'Log in', 'title' => 'Welcome back', 'description' => 'Log in to reconnect with the community.',
        'forgot_password' => 'Forgot your password?', 'remember' => 'Remember me', 'submit' => 'Log in',
        'no_account' => 'No account yet?', 'create_account' => 'Create an account',
    ],
    'forgot_password' => ['title' => 'Forgot your password?', 'description' => 'Receive a link to choose a new password.', 'submit' => 'Send password reset link', 'back' => 'Back to login'],
    'reset_password' => ['title' => 'Reset password', 'description' => 'Choose your new password below.', 'submit' => 'Reset password'],
    'confirm_password' => [
        'page_title' => 'Password confirmation', 'title' => 'Confirm your password',
        'description' => 'This is a secure area. Confirm your password to continue.', 'passkey' => 'Confirm with a passkey',
        'loading' => 'Confirming…', 'separator' => 'Or confirm with your password', 'submit' => 'Confirm',
    ],
    'verification' => [
        'page_title' => 'Email verification', 'title' => 'Verify your email address',
        'description' => 'Click the link we just sent you before creating your profile.',
        'sent' => 'A new verification link has been sent to your email address.', 'resend' => 'Resend verification email', 'logout' => 'Log out',
    ],
    'two_factor_challenge' => [
        'page_title' => 'Two-factor authentication', 'recovery_title' => 'Recovery code',
        'recovery_description' => 'Confirm access to your account with one of your recovery codes.',
        'auth_title' => 'Authentication code', 'auth_description' => 'Enter the code provided by your authentication app.',
        'use_auth' => 'use an authentication code', 'use_recovery' => 'use a recovery code', 'continue' => 'Continue', 'alternative' => 'You can also',
    ],
    'settings' => [
        'title' => 'Settings', 'description' => 'Manage your account, security, and appearance.', 'navigation' => 'Settings',
        'account' => 'Account', 'security' => 'Security', 'appearance' => 'Appearance', 'account_page_title' => 'Account settings',
        'account_description' => 'Update your login email address.', 'email_unverified' => 'Your email address is not verified.',
        'resend_verification' => 'Resend verification link', 'save' => 'Save',
        'appearance_description' => 'Choose the theme that is most comfortable for you.',
        'password_title' => 'Change password', 'password_description' => 'Use a long, unique password to protect your account.',
    ],
];
