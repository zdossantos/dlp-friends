<?php

$identity = 'The service is published and operated by Zacharie Dos Santos, sole trader, SIREN 104 531 819, SIRET 104 531 819 00019, at 28 rue Ernest Petit, 21000 Dijon, France.';

return [
    'terms' => [
        'meta' => ['title' => 'Terms of Use — DLP Friends', 'description' => 'Rules for using the DLP Friends friendship service.'],
        'title' => 'Terms of Use', 'date_label' => 'Effective version:', 'date' => '1 September 2026', 'toc_label' => 'Contents', 'contact_missing' => 'Contact address to configure', 'footer' => 'DLP Friends is independent and is not affiliated with Disney or Disneyland Paris.',
        'sections' => [
            ['id' => 'publisher', 'title' => '1. Publisher and contact', 'paragraphs' => [$identity, 'Contact: :email.'], 'items' => []],
            ['id' => 'purpose', 'title' => '2. Service purpose', 'paragraphs' => ['DLP Friends facilitates strictly friendly connections between adult Disneyland Paris fans. It is independent and is not affiliated with, endorsed by, or operated by Disney or Disneyland Paris.'], 'items' => []],
            ['id' => 'access', 'title' => '3. Access and adulthood', 'paragraphs' => ['Registration is limited to people aged 18 or over. Members provide accurate information, protect their credentials, and remain responsible for their accounts.'], 'items' => []],
            ['id' => 'conduct', 'title' => '4. Expected conduct', 'paragraphs' => ['Interactions must remain respectful, friendly, and lawful. Harassment, threats, hate, impersonation, fraud, spam, sexual or illegal content, and harm to another person’s rights or safety are prohibited.'], 'items' => []],
            ['id' => 'content', 'title' => '5. Profiles and messages', 'paragraphs' => ['Members remain responsible for their content. A private conversation opens only after mutual discovery. No result, meeting, or uninterrupted availability is guaranteed.'], 'items' => []],
            ['id' => 'moderation', 'title' => '6. Moderation', 'paragraphs' => ['For a confirmed breach, the publisher may proportionately remove content or restrict, suspend, or delete an account to protect members or comply with the law. No built-in reporting tool is claimed at this stage; contact :email.'], 'items' => []],
            ['id' => 'deletion', 'title' => '7. Account deletion', 'paragraphs' => ['Members may delete their account in settings. Data is removed immediately from active systems, subject to temporary backup rotation described in the Privacy Policy.'], 'items' => []],
            ['id' => 'liability', 'title' => '8. Availability and liability', 'paragraphs' => ['The service may be interrupted for maintenance or incidents. The publisher is not responsible for relationships between members, third-party services, or indirect loss, without excluding mandatory legal guarantees.'], 'items' => []],
            ['id' => 'ip', 'title' => '9. Intellectual property', 'paragraphs' => ['The DLP Friends brand, code, and original content remain protected. Members warrant that they hold rights to submitted content. Disney trademarks belong to their owners.'], 'items' => []],
            ['id' => 'changes', 'title' => '10. Changes and governing law', 'paragraphs' => ['These terms may change. The version accepted at registration is recorded. French law applies without removing mandatory consumer protections. Questions may be sent to :email.'], 'items' => []],
        ],
    ],
    'privacy' => [
        'meta' => ['title' => 'Privacy Policy — DLP Friends', 'description' => 'Data, purposes, retention, and member rights at DLP Friends.'],
        'title' => 'Privacy Policy', 'date_label' => 'Last updated:', 'date' => '5 September 2026', 'toc_label' => 'Contents', 'contact_missing' => 'Contact address to configure', 'footer' => 'DLP Friends protects only the data needed to provide the service.',
        'sections' => [
            ['id' => 'controller', 'title' => '1. Data controller', 'paragraphs' => [$identity, 'For privacy questions or requests: :email.'], 'items' => []],
            ['id' => 'data', 'title' => '2. Data and purposes', 'paragraphs' => ['We process account and security data (email, birth date, hashed password, locale, verification, passkeys, and two-factor authentication), profile data (display name, bio, avatar, visit frequency, interests, visibility), discoveries, blocks, matches, conversations and messages, and technical logs needed for security and operation.'], 'items' => ['Create and secure accounts and verify adulthood.', 'Display and rank profiles by shared interests.', 'Provide mutual discovery and messaging.', 'Prevent abuse, diagnose incidents, and comply with law.']],
            ['id' => 'bases', 'title' => '3. Legal bases', 'paragraphs' => ['Contract performance supports accounts, profiles, discovery, and messaging. Legitimate interests support security, abuse prevention, and proportionate technical improvement. Legal obligations may require retention. Consent is used where the law requires it.'], 'items' => []],
            ['id' => 'recipients', 'title' => '4. Recipients and hosting', 'paragraphs' => ['Data is available only to authorised people and necessary providers. The VPS is hosted by IONOS. MySQL, Redis, Reverb, and S3-compatible storage are self-hosted there. Resend sends transactional email. Google Analytics 4 provides audience measurement when configured. No advertising, payment, or OAuth service is currently enabled.'], 'items' => []],
            ['id' => 'retention', 'title' => '5. Retention', 'paragraphs' => ['Account data is kept for the account lifetime. Deletion removes it immediately from active systems. Encrypted daily MySQL and file backups are retained for :backup_days days and then expire automatically. Deleted data may remain there until expiry; backups are isolated and not restored for ordinary use. Daily technical logs follow operational configuration, 14 days by default.'], 'items' => []],
            ['id' => 'security', 'title' => '6. Security and transfers', 'paragraphs' => ['We use access controls, encrypted connections, password hashing, and encrypted backups. Google may process audience measurement data outside the European Economic Area under its contractual safeguards and privacy policy.'], 'items' => []],
            ['id' => 'cookies', 'title' => '7. Cookies and audience measurement', 'paragraphs' => ['Necessary storage supports sessions, security, language, and interface preferences. When Google Analytics 4 is configured, trackers measure viewed pages and journeys through the application. DLP Friends normalises dynamic URLs before sending them and deliberately sends no name, email address, message content, or member identifier. No advertising use is enabled.'], 'items' => []],
            ['id' => 'rights', 'title' => '8. Your rights', 'paragraphs' => ['You may request access, correction, erasure, restriction, objection, and, where applicable, portability at :email. You may define post-death instructions and complain to the French CNIL at cnil.fr. Proportionate identity verification may be requested.'], 'items' => []],
            ['id' => 'changes', 'title' => '9. Policy changes', 'paragraphs' => ['This policy may change to reflect the service or law. The update date and stable public links identify the published version.'], 'items' => []],
        ],
    ],
];
