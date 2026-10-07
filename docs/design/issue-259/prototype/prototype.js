/* Prototype de conception autonome : aucune requête ni mutation métier. */
const $ = (selector) => document.querySelector(selector);
const esc = (value) =>
    String(value).replace(
        /[&<>"']/g,
        (c) =>
            ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            })[c],
    );
const params = new URLSearchParams(location.search);
let lang = params.get('lang') === 'en' ? 'en' : 'fr';
let current =
    screens.find((s) => s.id === params.get('screen')) ||
    screens.find((s) => s.id === 'explore');
let state = params.get('state') || 'normal';
let theme = params.get('theme') === 'dark' ? 'dark' : 'light';
let season = ['halloween', 'christmas'].includes(params.get('season'))
    ? params.get('season')
    : 'standard';
let tutorialStep = 0;
let profileStep = 0;
let acceptedEvent = false;
let savedMessages = [];
let notificationFilter = 'all';
let allRead = false;
let pendingFocus = null;
const text = (fr, en) => (lang === 'fr' ? fr : en);
const words = {
    prototype: [
        'Maquette · non validée · données fictives',
        'Mockup · not approved · fictional data',
    ],
    screen: ['Écran', 'Screen'],
    theme: ['Apparence', 'Appearance'],
    season: ['Ambiance', 'Season'],
    state: ['État', 'State'],
    normal: ['Normal', 'Normal'],
    empty: ['Vide', 'Empty'],
    loading: ['Chargement', 'Loading'],
    error: ['Erreur', 'Error'],
    success: ['Succès', 'Success'],
    disabled: ['Indisponible', 'Unavailable'],
    readonly: ['Lecture seule', 'Read only'],
    selected: ['Sélectionné', 'Selected'],
    unread: ['Non lu', 'Unread'],
    light: ['Clair', 'Light'],
    dark: ['Sombre', 'Dark'],
    standard: ['Standard', 'Standard'],
    halloween: ['Halloween', 'Halloween'],
    christmas: ['Noël', 'Christmas'],
    close: ['Fermer', 'Close'],
    cancel: ['Annuler', 'Cancel'],
    back: ['Retour', 'Back'],
    save: ['Enregistrer', 'Save'],
    continue: ['Continuer', 'Continue'],
    retry: ['Réessayer', 'Try again'],
    explore: ['Explorer', 'Explore'],
    chats: ['Échanges', 'Conversations'],
    events: ['Événements', 'Events'],
    notifications: ['Notifications', 'Notifications'],
    profile: ['Profil', 'Profile'],
    pass: ['Passer', 'Pass'],
    discover: ['Découvrir', 'Discover'],
    about: ['À propos', 'About'],
    worlds: ['Univers favoris', 'Favourite worlds'],
    frequency: ['De temps en temps', 'From time to time'],
    bio: [
        'Toujours partante pour une journée entre amis et une pause pour discuter de nos univers favoris.',
        'Always up for a day with friends and a break to chat about our favourite worlds.',
    ],
    common: ['3 univers favoris en commun', '3 favourite worlds in common'],
    fictional: ['Avatar fictif · maquette', 'Fictional avatar · mockup'],
    music: ['Musique', 'Music'],
    shows: ['Spectacles', 'Shows'],
    food: ['Gastronomie', 'Food'],
    email: ['Adresse e-mail', 'Email address'],
    password: ['Mot de passe', 'Password'],
    send: ['Envoyer', 'Send'],
    message: ['Écris un message…', 'Write a message…'],
    today: ['Aujourd’hui', 'Today'],
    you: ['Moi', 'Me'],
    blocked: ['Profil bloqué', 'Blocked profile'],
    unblock: ['Débloquer', 'Unblock'],
    published: ['Version publiée', 'Published version'],
    draft: ['Brouillon', 'Draft'],
    pending: ['En attente de validation', 'Awaiting review'],
    submit: ['Soumettre à la validation', 'Submit for review'],
    approve: ['Approuver', 'Approve'],
    reject: ['Refuser', 'Reject'],
    reason: ['Motif de la décision', 'Reason for the decision'],
    members: ['Membres', 'Members'],
    catalogues: ['Catalogues', 'Catalogues'],
    partners: ['Partenaires', 'Partners'],
    spaces: ['Espaces', 'Spaces'],
    dashboard: ['Tableau de bord', 'Dashboard'],
    report: ['Signaler cet échange', 'Report this conversation'],
    block: ['Bloquer', 'Block'],
    delete: ['Supprimer', 'Delete'],
    search: ['Rechercher', 'Search'],
    active: ['Actif', 'Active'],
    archived: ['Archivé', 'Archived'],
    sent: ['Envoyée', 'Sent'],
    full: ['Complet', 'Full'],
    join: ['Demander à participer', 'Request to join'],
    accepted: ['Inscription acceptée', 'Registration accepted'],
    participants: ['Participants', 'Participants'],
    discussion: ['Discussion', 'Discussion'],
    settings: ['Réglages', 'Settings'],
    longName: [
        'Camille-Anne des Univers Partagés',
        'Camille-Anne of Shared Worlds',
    ],
    notify: [
        'Enregistrement simulé. Aucune donnée réelle modifiée.',
        'Simulated save. No real data changed.',
    ],
    loadMessage: ['Chargement en cours…', 'Loading…'],
    errorMessage: [
        'Impossible de charger ce contenu. Réessaie pour reprendre.',
        'This content could not be loaded. Try again to continue.',
    ],
    readonlyMessage: [
        'Cette discussion est archivée. Tu peux lire les messages, mais plus en envoyer.',
        'This discussion is archived. You can read messages but cannot send new ones.',
    ],
};
const t = (key) => {
    if (!words[key]) {
        throw new Error(`Missing prototype translation: ${key}`);
    }

    return words[key][lang === 'fr' ? 0 : 1];
};
const paths = {
    sparkle: 'm12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5Z',
    chat: 'M5 3h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H8l-5 3V5a2 2 0 0 1 2-2Z',
    calendar:
        'M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z',
    bell: 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-9 12a3 3 0 0 0 6 0',
    user: 'M20 21v-2a6 6 0 0 0-6-6h-4a6 6 0 0 0-6 6v2M16 6a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
    arrow: 'm9 18 6-6-6-6',
    back: 'm15 18-6-6 6-6',
    close: 'm6 6 12 12M18 6 6 18',
    check: 'm5 12 4 4L19 6',
    shield: 'M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z',
    send: 'm22 2-7 20-4-9-9-4 20-7ZM22 2 11 13',
    chart: 'M3 3v18h18M8 16v-4m5 4V8m5 8V5',
    store: 'M3 21h18M5 21V8h14v13M3 3h18l-2 5H5L3 3Zm6 18v-7h6v7',
    flag: 'M4 22V3m0 0c6-4 10 4 16 0v11c-6 4-10-4-16 0',
    ghost: 'M9 10h.01M15 10h.01M12 2a8 8 0 0 1 8 8v12l-4-3-4 3-4-3-4 3V10a8 8 0 0 1 8-8Z',
    snow: 'M12 2v20M3 7l18 10M3 17 21 7m-14-2 5 3 5-3m-10 14 5-3 5 3',
    heart: 'M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z',
};
function icon(name) {
    return `<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="${paths[name] || paths.sparkle}"/></svg>`;
}
function button(label, action = 'simulate', variant = '', ico = '') {
    return `<button class="btn ${variant}" data-action="${esc(action)}">${ico ? icon(ico) : ''}${esc(label)}</button>`;
}
function link(id, label, variant = '') {
    return `<a class="btn ${variant}" href="${url(id)}" data-screen="${id}">${esc(label)}</a>`;
}
function url(id) {
    return `?screen=${id}&lang=${lang}&theme=${theme}&season=${season}&state=normal`;
}
function badge(label, variant = '') {
    return `<span class="badge ${variant}">${esc(label)}</span>`;
}
function field(label, value = '', type = 'text', id = '', hint = '') {
    id = id || 'field-' + label.replace(/[^a-z0-9]/gi, '');

    return `<div class="field"><label for="${id}">${esc(label)}</label><input id="${id}" type="${type}" value="${esc(value)}" autocomplete="off">${hint ? `<small>${esc(hint)}</small>` : ''}</div>`;
}
function area(label, value = '', id = 'bio') {
    return `<div class="field"><label for="${id}">${esc(label)}</label><textarea id="${id}" maxlength="1000">${esc(value)}</textarea></div>`;
}
function check(label, id = 'consent', checked = false) {
    return `<label class="check"><input id="${id}" type="checkbox" ${checked ? 'checked' : ''}><span>${esc(label)}</span></label>`;
}
function avatar(initial = 'CA', rose = false) {
    return `<span class="avatar ${rose ? 'rose' : ''}" aria-label="${esc(t('fictional'))}">${esc(initial)}</span>`;
}
function pills() {
    return `<div class="pill-set">${['music', 'shows', 'food'].map((k) => badge(t(k), 'common')).join('')}</div>`;
}
function profileCard() {
    return `<article class="profile-card"><div class="portrait">${avatar('CA')}<small>${t('fictional')}</small>${season !== 'standard' ? icon(season === 'halloween' ? 'ghost' : 'snow').replace('<svg', '<svg class="season-icon"') : ''}</div><div class="profile-body"><div class="row spread"><h2>Camille</h2>${badge(text('28 ans', '28 years old'))}</div><p class="muted">${icon('sparkle')} ${t('common')}</p>${pills()}<p>${t('bio')}</p><div class="profile-footer">${t('frequency')}</div></div></article>`;
}
function head(sub = '') {
    return `<header class="page-head"><div><h1 tabindex="-1" id="page-title">${esc(current[lang])}</h1>${sub ? `<p class="muted">${esc(sub)}</p>` : ''}</div></header>`;
}
function stateNotice() {
    if (state === 'error') {
        return `<div class="notice error" role="alert">${t('errorMessage')}<div class="row">${button(t('retry'), 'retry', 'outline')}</div></div>`;
    }

    if (state === 'success') {
        return `<div class="notice success" role="status">${icon('check')} ${t('notify')}</div>`;
    }

    return '';
}
function emptyView() {
    return `<div class="empty">${icon('sparkle')}<h2>${text('Rien à afficher pour le moment', 'Nothing to show yet')}</h2><p class="muted">${text('Reviens un peu plus tard. Tes univers favoris nous aident à te proposer des profils.', 'Check back later. Your favourite worlds help us suggest profiles.')}</p>${link('edit-profile', text('Modifier mes univers favoris', 'Edit my favourite worlds'), 'outline')}</div>`;
}
function navigation() {
    if (['public', 'auth', 'shared'].includes(current.group)) {
        return '';
    }

    let entries =
        current.group === 'admin'
            ? [
                  ['dashboard', 'dashboard', 'chart'],
                  ['members', 'members', 'user'],
                  ['catalogues', 'catalogues', 'sparkle'],
                  ['partners-menu', 'partners', 'store'],
                  ['workspace', 'spaces', 'shield'],
              ]
            : current.group === 'partner'
              ? [
                    ['partner-profile', 'profile', 'store'],
                    [
                        'announcements',
                        text('Annonces', 'Announcements'),
                        'flag',
                    ],
                    ['statistics', text('Statistiques', 'Statistics'), 'chart'],
                    ['notifications', 'notifications', 'bell'],
                    ['workspace', 'spaces', 'shield'],
                ]
              : [
                    ['explore', 'explore', 'sparkle'],
                    ['chats', 'chats', 'chat'],
                    ['events', 'events', 'calendar'],
                    ['notifications', 'notifications', 'bell'],
                    ['profile', 'profile', 'user'],
                    ['workspace', 'spaces', 'shield'],
                ];

    return `<nav class="bottom-nav" aria-label="${text('Navigation principale', 'Main navigation')}">${entries
        .map(([id, label, ico]) => {
            const active =
                id === current.id ||
                (id === 'chats' && current.kind === 'chat') ||
                (id === 'events' && current.id.startsWith('event'));

            return `<a href="${url(id)}" ${id.endsWith('menu') || id === 'catalogues' ? `data-action="${id}"` : `data-screen="${id}"`} aria-label="${words[label] ? t(label) : esc(label)}" class="${active ? 'active' : ''}" ${active ? 'aria-current="page"' : ''}>${icon(ico)}<span class="nav-label">${id === 'events' ? text('Agenda', 'Events') : id === 'notifications' ? text('Alertes', 'Alerts') : id === 'chats' ? text('Échanges', 'Chats') : id === 'dashboard' ? text('Accueil', 'Home') : words[label] ? t(label) : esc(label)}</span></a>`;
        })
        .join('')}</nav>`;
}
function header() {
    return `<div class="app-header"><a class="brand" href="${url('home')}" data-screen="home"><img src="assets/logo${theme === 'dark' ? '-dark' : ''}.svg" alt=""><span>DLP Friends</span></a>${['public', 'auth'].includes(current.group) ? link(current.group === 'public' ? 'login' : 'home', text(current.group === 'public' ? 'Se connecter' : 'Accueil', current.group === 'public' ? 'Log in' : 'Home'), 'quiet') : button(t('spaces'), 'workspace', 'quiet', 'shield')}</div>`;
}
function publicFooter() {
    return `<footer class="footer"><p>${text('18 ans et plus · Rencontres strictement amicales. Projet indépendant, non affilié à Disney ou Disneyland Paris.', 'Adults 18+ · Strictly friendly connections. An independent project, not affiliated with Disney or Disneyland Paris.')}</p><div class="public-links">${['faq', 'matching', 'news', 'terms', 'privacy', 'cookies'].map((id) => link(id, screens.find((s) => s.id === id)[lang], 'quiet')).join('')}</div></footer>`;
}
function home() {
    return `<section class="hero"><div><h1>${text('Des univers partagés.<br>Des amitiés à découvrir.', 'Shared worlds.<br>New friends to discover.')}</h1><p class="muted">${text('Retrouve des fans majeurs de Disneyland Paris qui partagent tes univers favoris. Pour discuter, se découvrir et profiter de journées entre amis.', 'Meet adult Disneyland Paris fans who share your favourite worlds. Chat, get to know one another and enjoy days with friends.')}</p><div class="row">${link('register', text('Créer mon compte', 'Create my account'))}${link('matching', text('Comment ça marche', 'How it works'), 'outline')}</div><div class="hero-proof">${icon('shield')} ${text('Amical. Réservé aux adultes. À ton rythme.', 'Friendly. Adults only. At your own pace.')}</div></div><div class="hero-preview"><h2>${text('Vos univers se croisent', 'Your worlds cross')}</h2><div class="panel"><div class="row">${avatar()}<div><strong>Camille</strong><p class="muted">${t('common')}</p></div></div>${pills()}<p class="section">${text('Camille souhaite aussi te découvrir. Tu peux maintenant commencer l’échange.', 'Camille would also like to get to know you. You can now start a conversation.')}</p></div></div></section><section class="section reading"><h2>${text('Une découverte qui te ressemble', 'Discovery that feels like you')}</h2><p>${text('Choisis tes univers favoris, explore les profils et exprime ton envie de découvrir une personne. Deux likes réciproques ouvrent un échange privé.', 'Choose your favourite worlds, explore profiles and express your wish to meet someone. Two mutual likes open a private conversation.')}</p>${link('faq', text('Tes questions, nos réponses', 'Your questions, answered'), 'quiet')}</section><section class="section"><h2>${t('partners')}</h2><p class="muted">${text('Exemple d’une section avec fiches publiées. Elle disparaît sans partenaire publié.', 'Example with published profiles. This section is omitted when none are published.')}</p><div class="event-grid section"><article class="event-card"><h3>${text('Les Carnets de Visite', 'Visit Journals')}</h3><p>${text('Un atelier indépendant pour garder les souvenirs de tes journées.', 'An independent workshop for preserving memories of your days.')}</p></article></div></section>${publicFooter()}`;
}
function reading() {
    let content = '';

    if (current.id === 'faq') {
        content = [
            [
                'Est-ce une application de rencontres amoureuses ?',
                'Is this a dating app?',
                'Non. DLP Friends est réservé aux rencontres strictement amicales entre adultes.',
                'No. DLP Friends is for strictly friendly connections between adults.',
            ],
            [
                'Comment un échange commence-t-il ?',
                'How does a conversation start?',
                'Deux likes réciproques ouvrent un échange privé. Une assistance administrative constitue l’exception prévue.',
                'Two mutual likes open a private conversation. Administrative support is the documented exception.',
            ],
            [
                'Puis-je masquer mon profil ?',
                'Can I hide my profile?',
                'Oui, depuis ton profil. Le masquage retire les suggestions ; le blocage interdit les échanges.',
                'Yes, from your profile. Hiding removes suggestions; blocking prevents conversations.',
            ],
        ]
            .map(
                (r) =>
                    `<details><summary>${text(r[0], r[1])}</summary><p>${text(r[2], r[3])}</p></details>`,
            )
            .join('');
    } else if (current.id === 'matching') {
        content = `<h2>${text('Les univers communs en premier', 'Shared worlds first')}</h2><p>${text('Les profils disponibles sont classés par nombre d’intérêts actifs communs. La fréquence commune peut ensuite départager selon le réglage administrateur. L’âge et la localisation ne classent pas les profils.', 'Available profiles are ranked by shared active interests. Shared visit frequency can break ties when enabled by an administrator. Age and location do not rank profiles.')}</p><h2>${text('Une envie réciproque', 'A mutual wish')}</h2><p>${text('Passer retire la carte. Découvrir exprime ton envie d’échanger. Lorsque le like est réciproque, vos univers se croisent.', 'Pass removes the card. Discover expresses your wish to chat. When the like is mutual, your worlds cross.')}</p>`;
    } else if (current.id === 'news') {
        content = `<h2>${text('Les nouveautés, à ton rythme', 'What’s new, at your pace')}</h2><p class="muted">${text('Exemple de composition. Les versions et fonctionnalités seront reprises du catalogue de nouveautés existant, sans chiffres inventés.', 'Layout example. Versions and features will come from the existing release notes catalogue, without invented figures.')}</p><article class="section"><h3>${text('Des échanges plus faciles à suivre', 'Conversations that are easier to follow')}</h3><p>${text('Expéditeurs identifiés, séparateurs de jour et ambiances adaptées aux saisons.', 'Identified senders, day separators and seasonal themes.')}</p></article>`;
    } else if (['terms', 'privacy'].includes(current.id)) {
        content = `<div class="notice">${text('Extrait de mise en page. Le texte légal intégral existant sera conservé. Version actuelle : 2026-10-04.', 'Layout excerpt. The existing complete legal text will be preserved. Current version: 2026-10-04.')}</div><div class="public-links"><a href="#legal-access">${text('Accès au service', 'Service access')}</a><a href="#legal-data">${text('Tes données', 'Your data')}</a></div><h2 id="legal-access">${text('Accès au service', 'Service access')}</h2><p>${text('Le service est réservé aux personnes majeures et aux rencontres strictement amicales.', 'The service is restricted to adults and strictly friendly connections.')}</p><h2 id="legal-data">${text('Tes données et tes choix', 'Your data and choices')}</h2><p>${text('Tu peux modifier ton profil, le masquer et demander la suppression de ton compte. Consulte la politique de confidentialité pour les conditions et durées de conservation.', 'You can edit your profile, hide it and request account deletion. See the privacy policy for conditions and retention periods.')}</p>`;
    } else {
        content = `<h2>${text('Partager une journée entre amis', 'Share a day with friends')}</h2><p>${text('DLP Friends aide les fans majeurs à découvrir des personnes qui partagent leurs intérêts, puis à échanger lorsqu’ils se choisissent réciproquement.', 'DLP Friends helps adult fans discover people with shared interests and chat when they mutually choose one another.')}</p><h2>${text('Garder le contrôle', 'Stay in control')}</h2><p>${text('Avance à ton rythme. Le blocage et les réglages de visibilité restent accessibles.', 'Take it at your own pace. Blocking and visibility settings remain accessible.')}</p>`;
    }

    return `${head()}<article class="reading">${content}<div class="section">${link('register', text('Créer mon compte', 'Create my account'))}</div></article>${publicFooter()}`;
}
function auth() {
    const id = current.id;
    let fields = '',
        cta = t('continue'),
        target = 'explore';

    if (id === 'login') {
        fields =
            field(t('email'), 'camille@example.test', 'email') +
            field(t('password'), '', 'password') +
            link(
                'forgot',
                text('Mot de passe oublié ?', 'Forgot your password?'),
                'quiet',
            );
        cta = text('Se connecter', 'Log in');
    }

    if (id === 'register' || id === 'social') {
        fields =
            (id === 'register'
                ? field(t('email'), '', 'email') +
                  field(t('password'), '', 'password') +
                  field(
                      text('Confirme le mot de passe', 'Confirm password'),
                      '',
                      'password',
                      'password-confirm',
                  )
                : '') +
            field(
                text('Date de naissance', 'Date of birth'),
                '',
                'date',
                'birth-date',
                text(
                    'Le service est réservé aux personnes de 18 ans et plus.',
                    'The service is for people aged 18 or over.',
                ),
            ) +
            check(
                text(
                    'J’accepte les conditions générales d’utilisation et la politique de confidentialité.',
                    'I accept the terms of use and privacy policy.',
                ),
                'accept-terms',
            ) +
            `<div class="row">${link('terms', text('Lire les conditions', 'Read the terms'), 'quiet')}${link('privacy', text('Lire la politique', 'Read the policy'), 'quiet')}</div>`;
        target = id === 'register' ? 'verify' : 'create-profile';
    }

    if (id === 'forgot') {
        fields = field(t('email'), '', 'email');
        cta = text('Envoyer le lien de réinitialisation', 'Send a reset link');
        target = 'reset';
    }

    if (id === 'reset') {
        fields =
            field(t('password'), '', 'password') +
            field(
                text('Confirme le mot de passe', 'Confirm password'),
                '',
                'password',
                'confirm-new',
            );
        target = 'login';
    }

    if (id === 'confirm') {
        fields = field(t('password'), '', 'password');
        target = 'security';
    }

    if (id === 'verify') {
        fields = `<div class="notice">${text('Un lien a été envoyé à camille@example.test. Ouvre-le pour poursuivre.', 'A link was sent to camille@example.test. Open it to continue.')}</div>${button(text('Renvoyer le lien', 'Resend the link'), 'simulate', 'outline full')}<p class="section">${text('Lien de démonstration :', 'Demo link:')} ${link('create-profile', text('Adresse vérifiée', 'Email verified'), 'quiet')}</p>`;
        cta = text('Se déconnecter', 'Log out');
        target = 'login';
    }

    if (id === '2fa') {
        fields =
            field(
                text('Code d’authentification', 'Authentication code'),
                '',
                'text',
                'auth-code',
            ) +
            button(
                text('Utiliser un code de récupération', 'Use a recovery code'),
                'recovery',
                'quiet',
            );
    }

    if (id === 'accept') {
        fields = `<div class="notice">${text('Les conditions ont été mises à jour. Leur acceptation explicite est nécessaire avant de retrouver ton espace.', 'The terms have changed. Explicit acceptance is required before accessing your workspace.')}</div><p>2026-10-04</p><div class="row">${link('terms', text('Lire les conditions', 'Read the terms'), 'quiet')}${link('privacy', text('Lire la politique', 'Read the policy'), 'quiet')}</div>${check(text('J’accepte cette version des conditions.', 'I accept this version of the terms.'), 'accept-terms')}`;
    }

    const consent = ['register', 'social', 'accept'].includes(id);

    return `<div class="auth-layout"><aside class="auth-aside"><h2>${text('L’aventure se partage.', 'Adventure is shared.')}</h2><p class="muted">${text('Des univers communs, des échanges choisis et des journées entre amis.', 'Shared worlds, chosen conversations and days with friends.')}</p><div class="section">${profileCard()}</div></aside><section class="auth-form"><h1 id="page-title" tabindex="-1">${esc(current[lang])}</h1><p class="muted">${text('Un espace réservé aux adultes, pour des rencontres amicales.', 'An adults-only space for friendly connections.')}</p>${stateNotice()}<form data-form="auth">${fields}<button type="submit" class="btn full" data-next="${target}" ${consent ? 'disabled data-consent="accept-terms"' : ''}>${cta}</button></form>${['login', 'register'].includes(id) ? `<div class="divider">${text('ou', 'or')}</div>${link('social', text('Continuer avec Google', 'Continue with Google'), 'outline full')}<div class="section">${link(id === 'login' ? 'register' : 'login', text(id === 'login' ? 'Créer un compte' : 'J’ai déjà un compte', id === 'login' ? 'Create an account' : 'I already have an account'), 'quiet full')}${id === 'login' ? link('2fa', text('Voir la vérification en deux étapes', 'Preview two-factor verification'), 'quiet full') : ''}</div>` : ''}</section></div>`;
}
function profileForm() {
    const steps = [
        text('Avatar', 'Avatar'),
        text('Ton profil', 'Your profile'),
        t('worlds'),
        text('Aperçu', 'Preview'),
    ];

    if (current.id === 'edit-profile') {
        profileStep = Math.min(profileStep, 3);
    }

    let body = '';

    if (profileStep === 0) {
        body = `<h2>${steps[0]}</h2><p class="muted">${text('Choisis un avatar actif du catalogue. Les initiales ci-dessous remplacent les images privées dans la maquette.', 'Choose an active catalogue avatar. These initials stand in for private images in the mockup.')}</p><div class="row section">${['CA', 'ML', 'AJ'].map((v, i) => `<button class="btn ${i === 0 ? 'secondary' : 'outline'}" data-action="avatar-select" aria-pressed="${i === 0}">${avatar(v, i === 1)}${i === 0 ? icon('check') : ''}</button>`).join('')}</div>`;
    } else if (profileStep === 1) {
        body =
            field(
                text('Nom d’affichage', 'Display name'),
                t('longName'),
                'text',
                'display-name',
            ) +
            area(text('Ta bio', 'Your bio'), t('bio')) +
            `<div class="field"><label for="frequency">${text('Fréquence de visite', 'Visit frequency')}</label><select id="frequency"><option>${t('frequency')}</option><option>${text('Plusieurs fois par an', 'Several times a year')}</option></select></div>`;
    } else if (profileStep === 2) {
        body = `<h2>${t('worlds')}</h2><p class="muted">${text('3 sur 5 sélectionnés · limite illustrative du réglage actuel', '3 of 5 selected · illustrative current limit')}</p>${['music', 'shows', 'food'].map((k) => check(t(k), 'interest-' + k, true)).join('')}${check(text('Collections', 'Collections'), 'interest-collections')}${check(text('Histoires', 'Stories'), 'interest-stories')}`;
    } else {
        body =
            profileCard() +
            check(
                text('Rendre mon profil visible', 'Make my profile visible'),
                'visible',
                true,
            ) +
            `${current.id === 'edit-profile' ? `<section class="section"><h2>${text('Liens sociaux', 'Social links')}</h2><p>${text('Trois liens maximum. Un seul par réseau.', 'Up to three links. One per network.')}</p>${field('Instagram', 'https://www.instagram.com/exemple', 'url', 'social-url')}<div class="field"><label for="social-visibility">${text('Visibilité des liens', 'Link visibility')}</label><select id="social-visibility"><option>${text('Mes univers croisés', 'My crossed worlds')}</option><option>${text('Masqués', 'Hidden')}</option><option>${text('Tous les membres — contact possible avant un match', 'All members — contact is possible before a match')}</option></select></div></section>` : ''}`;
    }

    return `${head(text('Un avatar, une présentation, tes univers favoris.', 'An avatar, an introduction, your favourite worlds.'))}<div class="narrow"><div class="step-line" aria-label="${esc(steps[profileStep])}">${steps.map((_, i) => `<span class="${i <= profileStep ? 'done' : ''}"></span>`).join('')}</div><p class="muted">${profileStep + 1} / 4 · ${steps[profileStep]}</p><div class="section">${body}</div><div class="row section">${profileStep > 0 ? button(t('back'), 'profile-back', 'outline') : ''}${button(profileStep === 3 ? t('save') : t('continue'), 'profile-next')}</div></div>`;
}
function discovery() {
    return `${head(text('Des personnes qui partagent tes univers favoris.', 'People who share your favourite worlds.'))}<div class="discovery-layout"><div>${profileCard()}<div class="decision-actions">${button(t('pass'), 'pass', 'outline', 'close')}${button(t('discover'), 'discover', '', 'sparkle')}</div>${link('member-profile', text('Voir le profil complet', 'View full profile'), 'quiet full')}</div><aside><h2>${text('La curiosité, à ton rythme', 'Curiosity, at your pace')}</h2><p class="muted">${text('Tes univers communs donnent un point de départ. L’échange s’ouvre seulement si l’envie de se découvrir est réciproque.', 'Your shared worlds offer a starting point. A conversation opens only when the wish to get to know each other is mutual.')}</p><div class="section">${link('passed', text('Voir les profils passés', 'View passed profiles'), 'outline')}</div>${link('matching', text('Comprendre le classement', 'Understand the ranking'), 'quiet')}</aside></div>`;
}
function profile() {
    const own = current.id === 'profile';

    return `${head()}<div class="narrow">${profileCard()}<section class="section"><h2>${t('about')}</h2><p>${t('bio')}</p></section><section class="section"><h2>${t('worlds')}</h2>${pills()}</section>${own ? `<div class="row section">${link('edit-profile', text('Modifier mon profil', 'Edit my profile'))}${link('cookies', text('Gérer les cookies', 'Manage cookies'), 'outline')}</div>${check(text('Mon profil est visible', 'My profile is visible'), 'visibility', true)}${link('account', t('settings'), 'quiet')}` : `<div class="row section">${button(t('discover'), 'discover', '', 'sparkle')}${button(t('block'), 'block', 'outline', 'shield')}</div><p class="muted section">${text('Exemple : aucun lien social exposé avant un match lorsque la visibilité est « univers croisés ».', 'Example: no social links are exposed before a match when visibility is restricted to crossed worlds.')}</p>`}</div>`;
}
function people() {
    let names = ['Camille', 'Morgan', t('longName')];

    return `${head()}${current.id === 'participants' ? `<p class="notice">${t('accepted')} · ${text('Les participants et le lieu précis sont maintenant visibles.', 'Participants and the exact location are now visible.')}</p>` : ''}<div class="list">${names.map((name, i) => `<article class="list-item ${i === 1 && current.id === 'participants' ? 'blocked' : state === 'unread' && i === 0 ? 'unread' : ''}">${avatar(['CA', 'MO', 'AJ'][i], i === 1)}<div class="grow"><strong>${esc(current.id === 'participants' && i === 2 ? t('you') : name)}</strong><p class="muted">${current.id === 'chats' ? text('On se retrouve samedi ?', 'Shall we meet on Saturday?') : i === 1 && current.id === 'participants' ? t('blocked') : t('common')}</p><div class="row">${current.id === 'requests' ? button(t('approve'), 'accept-request') + button(t('reject'), 'reject-request', 'outline') : i === 1 && current.id === 'participants' ? button(t('unblock'), 'unblock', 'outline') : button(current.id === 'chats' ? text('Ouvrir l’échange', 'Open conversation') : text('Voir le profil', 'View profile'), current.id === 'chats' ? 'open-chat' : 'open-profile', 'outline')}${current.id === 'participants' && i === 0 ? button(t('discover'), 'discover') : ''}</div></div>${current.id === 'chats' && i === 0 ? badge(text('2 non lus', '2 unread'), 'common') : ''}</article>`).join('')}</div><div class="row section">${button(text('Précédent', 'Previous'), 'simulate', 'outline')}${badge('1')}${button(text('Suivant', 'Next'), 'simulate', 'outline')}</div>`;
}
function match() {
    return `<div class="empty"><div class="match-art">${avatar('ML', true)}${icon('sparkle')}${avatar('CA')}</div><h1 id="page-title" tabindex="-1">${text('Vos univers se croisent', 'Your worlds cross')}</h1><p>${text('Camille souhaite aussi te découvrir. Tu peux maintenant commencer l’échange.', 'Camille would also like to get to know you. You can now start a conversation.')}</p>${link(current.id === 'tutorial-match' ? 'tutorial-chat' : 'chat', text('Commencer l’échange', 'Start conversation'))}${link('explore', text('Continuer à explorer', 'Continue exploring'), 'quiet full')}</div>`;
}
function chat() {
    const group = current.id === 'group-chat';

    return `<div class="chat-layout"><div class="chat-head"><div class="row">${link(group ? 'event' : 'chats', t('back'), 'quiet')}${avatar()}<div><h1 id="page-title" tabindex="-1" style="font-size:24px">${group ? text('Une journée entre amis', 'A day with friends') : 'Camille'}</h1><small>${group ? text('Discussion réservée aux participants acceptés', 'Accepted participants only') : text('Échange privé', 'Private conversation')}</small></div></div>${group ? '' : button(t('report'), 'report', 'outline', 'flag')}</div>${state === 'readonly' ? `<p class="notice section">${t('readonlyMessage')}</p>` : state === 'error' ? stateNotice() : ''}<div class="timeline"><p class="day">${t('today')}</p>${state === 'empty' ? `<div class="empty"><h2>${text('Un premier mot suffit', 'A first word is enough')}</h2><p>${text('Choisis une amorce. Elle préremplit ton brouillon ; tu choisis quand l’envoyer.', 'Choose a starter. It fills your draft; you choose when to send it.')}</p>${['music', 'shows', 'food'].map((k) => button(text('Parlons de ', 'Let’s talk about ') + t(k), 'starter:' + k, 'outline')).join('')}</div>` : `<article class="message"><small>Camille · 10:42</small><div class="bubble">${text('Salut ! On prépare notre prochaine journée entre amis ?', 'Hi! Shall we plan our next day with friends?')}</div>${button(text('J’aime · 1', 'Like · 1'), 'like-message', 'quiet', 'heart')}</article><article class="message mine"><small>${t('you')} · 10:44</small><div class="bubble">${text('Avec plaisir, je suis disponible samedi.', 'Absolutely, I’m free on Saturday.')}</div></article>`}${savedMessages.map((m) => `<article class="message mine"><small>${t('you')}</small><div class="bubble">${esc(m)}</div></article>`).join('')}</div><form class="composer" data-form="message"><div class="composer-wrap"><label for="message" class="muted">${t('message')}</label><textarea id="message" maxlength="2000" ${['readonly', 'disabled'].includes(state) ? 'disabled' : ''}></textarea><small id="message-count">0 / 2 000</small></div><button id="send-message" class="btn icon-button" disabled aria-label="${t('send')}">${icon('send')}</button></form></div>`;
}
function events() {
    const mine = current.id === 'my-events';

    return `${head(text('Des moments à partager, sans pression.', 'Moments to share, without pressure.'))}<div class="tabs">${link('events', t('discover'), mine ? 'outline' : 'selected')}${link('my-events', text('Mes événements', 'My events'), mine ? 'selected' : 'outline')}</div><div class="row spread section"><h2>${mine ? text('Tu organises', 'You organise') : text('À venir', 'Coming up')}</h2>${link('event-create', text('Créer un événement', 'Create event'), 'outline')}</div><div class="event-grid section">${['Une journée entre amis', 'Une pause pour discuter', 'Nos univers favoris'].map((name, i) => `<article class="event-card"><div class="row spread"><div class="date-tile"><b>${17 + i * 7}</b><span>${text('OCT.', 'OCT')}</span></div>${badge(mine ? text('Organisateur', 'Organiser') : text('Sur validation', 'Approval required'))}</div><h2>${text(name, ['A day with friends', 'A break to chat', 'Our favourite worlds'][i])}</h2><p class="muted">Disneyland Paris · ${text('Lieu précis après acceptation', 'Exact location after acceptance')}</p><div class="event-summary"><div class="people-stack">${avatar('CA')}${avatar('ML')}</div><span>${text('2 / 6 places occupées', '2 / 6 places taken')}</span></div>${button(text('Voir l’événement', 'View event'), 'event-detail', 'outline full')}</article>`).join('')}</div>${mine ? `<section class="section"><h2>${text('Tu participes', 'You participate')}</h2><p class="muted">${text('Aucun autre événement rejoint dans cet exemple. Les événements complets restent accessibles ici.', 'No other joined events in this example. Full events remain accessible here.')}</p></section>` : ''}`;
}
function eventDetail() {
    return `<div class="row">${badge(text('17 octobre · 10:00', '17 October · 10:00'))}${badge(acceptedEvent ? t('accepted') : text('Sur validation', 'Approval required'))}</div><h2 class="section">${text('Une journée entre amis', 'A day with friends')}</h2><p class="section">${text('Une journée tranquille pour échanger autour de nos univers favoris.', 'A relaxed day to chat about our favourite worlds.')}</p><div class="list section"><div class="list-item"><div class="grow"><strong>${text('Lieu', 'Location')}</strong><p>${acceptedEvent ? text('Point de rencontre fictif communiqué aux participants', 'Fictional meeting point shared with participants') : 'Disneyland Paris'}</p><small>${acceptedEvent ? '' : text('Le lieu précis sera visible après acceptation.', 'The exact location will be visible after acceptance.')}</small></div></div><div class="list-item"><div class="grow"><strong>${text('Places occupées', 'Places taken')}</strong><p>2 / 6 · ${text('organisateur inclus', 'organiser included')}</p></div></div></div><div class="row section">${acceptedEvent ? button(t('participants'), 'participants', 'outline') + link('group-chat', t('discussion')) : button(t('join'), 'join-event')}</div><div class="section row">${button(text('Voir en tant que participant accepté', 'Preview as accepted participant'), 'event-accepted', 'quiet')}${link('event-edit', text('Vue organisateur : modifier', 'Organiser view: edit'), 'quiet')}${link('requests', text('Vue organisateur : demandes', 'Organiser view: requests'), 'quiet')}${button(text('Annuler l’événement', 'Cancel event'), 'cancel-event', 'outline')}</div><p class="muted section">${text('Les boutons de rôle servent à la revue des maquettes. En production, les autorisations serveur déterminent les actions visibles.', 'Role buttons are for mockup review. In production, server permissions determine visible actions.')}</p>`;
}
function eventForm() {
    return `${field(text('Titre', 'Title'), text('Une journée entre amis', 'A day with friends'), 'text', 'event-title')}${area(text('Description', 'Description'), text('Une journée tranquille entre adultes pour échanger.', 'A relaxed day for adults to chat.'), 'event-description')}<div class="form-columns">${field(text('Date et heure', 'Date and time'), '2026-10-17T10:00', 'datetime-local', 'event-date')}${field(text('Capacité totale', 'Total capacity'), '6', 'number', 'event-capacity')}</div>${field(text('Lieu général', 'General location'), 'Disneyland Paris', 'text', 'general-location')}${field(text('Lieu précis — réservé aux participants acceptés', 'Exact location — accepted participants only'), '', 'text', 'private-location')}<div class="field"><label for="registration-mode">${text('Inscription', 'Registration')}</label><select id="registration-mode" ${current.id === 'event-edit' ? 'disabled' : ''}><option>${text('Sur validation de l’organisateur', 'Organiser approval')}</option><option>${text('Automatique', 'Automatic')}</option></select></div><p class="notice">${text('Date, lieux et capacité : modifications jusqu’à 24 h avant le début. Le mode se verrouille dès la première demande.', 'Date, locations and capacity can change until 24 hours before the start. Mode locks after the first request.')}</p>${button(t('save'), 'save-event')}`;
}
function notifications() {
    return `${head()}<div class="tabs">${button(text('Toutes', 'All'), 'notifications-all', notificationFilter === 'all' ? 'selected' : 'outline')}${button(t('unread'), 'notifications-unread', notificationFilter === 'unread' ? 'selected' : 'outline')}${button(t('events'), 'notifications-events', notificationFilter === 'events' ? 'selected' : 'outline')}</div>${button(text('Tout marquer comme lu', 'Mark all as read'), 'read-all', 'quiet')}<div class="list section">${(notificationFilter === 'events' ? ['event'] : notificationFilter === 'unread' ? (allRead ? [] : ['chat', 'event']) : ['chat', 'event', 'announcement', 'admin']).map((kind, i) => `<article class="list-item ${i < 2 ? 'unread' : ''}">${icon(kind === 'event' ? 'calendar' : kind === 'chat' ? 'chat' : 'bell')}<div class="grow"><strong>${kind === 'event' ? text('Ta demande a été acceptée', 'Your request was accepted') : kind === 'chat' ? text('Camille t’a envoyé un message', 'Camille sent you a message') : kind === 'admin' ? text('Administration : un nouveau membre', 'Administration: a new member') : text('Les Carnets de Visite · annonce', 'Visit Journals · announcement')}</strong><small>${i < 2 ? t('unread') : text('Lu', 'Read')} · 10:45</small><div class="row">${button(text('Ouvrir', 'Open'), kind === 'event' ? 'event-detail' : kind === 'chat' ? 'open-chat' : kind === 'admin' ? 'open-members' : 'open-announcement', 'quiet')}${kind === 'announcement' ? button(text('Retirer l’annonce', 'Dismiss announcement'), 'dismiss-announcement', 'outline') : ''}</div></div></article>`).join('')}</div>`;
}
function settings() {
    const ids = ['account', 'security', 'appearance', 'preferences'];
    let body = '';

    if (current.id === 'account') {
        body = `<h2>${text('Tes informations', 'Your information')}</h2>${field(t('email'), 'camille@example.test', 'email')}<div class="section">${button(t('save'))}</div><section class="section"><h2>${text('Récupérer tes données', 'Get your data')}</h2><p>${text('Télécharge les données incluses dans ton export au format JSON. Aucun fichier n’est conservé côté serveur.', 'Download the data included in your export as JSON. No file is retained on the server.')}</p>${button(text('Exporter mes données', 'Export my data'), 'export', 'outline')}</section><section class="section"><h2>${text('Supprimer mon compte', 'Delete my account')}</h2><p>${text('Ton accès cesse immédiatement. Les données sont supprimées après 30 jours, selon les règles de conservation.', 'Your access ends immediately. Data is deleted after 30 days, subject to retention rules.')}</p>${button(text('Demander la suppression', 'Request deletion'), 'delete-account', 'outline')}</section>`;
    }

    if (current.id === 'security') {
        body = `<h2>${text('Mot de passe', 'Password')}</h2>${field(text('Mot de passe actuel', 'Current password'), '', 'password')}${field(text('Nouveau mot de passe', 'New password'), '', 'password', 'new-password')}${button(t('save'))}<section class="section"><h2>${text('Authentification en deux étapes', 'Two-factor authentication')}</h2><p>${text('Ajoute une vérification avec une application d’authentification.', 'Add verification using an authenticator app.')}</p>${button(text('Configurer', 'Set up'), 'setup-2fa', 'outline')}</section><section class="section"><h2>${text('Clés d’accès', 'Passkeys')}</h2>${button(text('Ajouter une clé d’accès', 'Add a passkey'), 'simulate', 'outline')}</section>`;
    }

    if (current.id === 'appearance') {
        body = `<h2>${text('Un affichage confortable', 'A comfortable display')}</h2><div class="row section">${['light', 'dark', 'system'].map((k) => button(text(k === 'system' ? 'Système' : k === 'light' ? 'Clair' : 'Sombre', k === 'system' ? 'System' : k === 'light' ? 'Light' : 'Dark'), 'theme:' + k, k === theme ? '' : 'outline')).join('')}</div><p class="muted section">${text('Les ambiances saisonnières sont choisies par l’administration, indépendamment de ta préférence clair / sombre.', 'Seasonal themes are selected by administration, independently of your light / dark preference.')}</p>`;
    }

    if (current.id === 'preferences') {
        body = `<h2>${text('Choisis tes notifications', 'Choose your notifications')}</h2>${['Univers croisés', 'Messages', 'Événements', 'Annonces partenaires', 'Administration'].map((s, i) => check(text(s, ['Crossed worlds', 'Messages', 'Events', 'Partner announcements', 'Administration'][i]), 'pref-' + i, true)).join('')}<small>${text('La préférence Administration n’apparaît que pour un administrateur autorisé.', 'The Administration preference appears only for an authorised administrator.')}</small><section class="section"><h2>${text('Cet appareil', 'This device')}</h2><p>${text('Les notifications Push nécessitent ton accord sur chaque appareil.', 'Push notifications require your permission on each device.')}</p>${button(text('Activer sur cet appareil', 'Enable on this device'), 'push', 'outline')}${button(text('Retirer cet appareil', 'Remove this device'), 'remove-device', 'quiet')}</section>`;
    }

    return `${head()}<div class="settings-layout"><nav class="settings-nav" aria-label="${t('settings')}">${ids.map((id) => link(id, screens.find((s) => s.id === id)[lang], id === current.id ? '' : 'outline')).join('')}</nav><div>${body}</div></div>`;
}
function partner() {
    return `${head(text('Ton brouillon reste distinct de la version publiée.', 'Your draft remains separate from the published version.'))}<div class="form-columns"><section class="panel"><div class="row spread"><h2>${t('published')}</h2>${badge(text('Approuvée', 'Approved'))}</div><strong>${text('Les Carnets de Visite', 'Visit Journals')}</strong><p class="muted">${text('Un atelier indépendant pour tes souvenirs.', 'An independent workshop for your memories.')}</p></section><section class="panel"><h2>${text('Dernière soumission', 'Latest submission')}</h2>${badge(t('pending'))}<p class="muted section">${text('La version publiée ne change pas pendant la modération.', 'The published version stays unchanged during review.')}</p></section></div><section class="section"><h2>${text('Modifier le brouillon', 'Edit draft')}</h2><div class="form-columns section">${['fr', 'en'].map((k) => `<fieldset><legend>${k === 'fr' ? 'Français' : 'English'}</legend>${field(text('Nom en ', 'Name in ') + (k === 'fr' ? text('français', 'French') : text('anglais', 'English')), k === 'fr' ? 'Les Carnets de Visite' : 'Visit Journals', 'text', 'partner-name-' + k)}${area(text('Description en ', 'Description in ') + (k === 'fr' ? text('français', 'French') : text('anglais', 'English')), k === 'fr' ? 'Un atelier indépendant pour garder tes souvenirs.' : 'An independent workshop for preserving your memories.', 'partner-description-' + k)}</fieldset>`).join('')}</div><div class="field"><label for="partner-image">${text('Image de la fiche', 'Profile image')}</label><input id="partner-image" type="file" accept="image/png,image/jpeg,image/webp"><small>${text('Image contrôlée côté serveur, sans image distante. Les limites existantes restent applicables.', 'Server-validated image, without remote images. Existing limits remain applicable.')}</small></div><div class="row">${button(t('save'))}${button(t('submit'), 'submit-partner', 'outline')}</div></section>`;
}
function announcements() {
    return `${head(text('Prépare un brouillon, puis soumets-le à la modération.', 'Prepare a draft, then submit it for review.'))}${link('announcement-edit', text('Créer une annonce', 'Create announcement'))}<div class="list section">${['draft', 'pending', 'sent'].map((k) => `<article class="list-item"><div class="grow"><strong>${text('Des carnets pour tes journées', 'Journals for your days')}</strong><p class="muted">${t(k)} · ${text('exemple fictif', 'fictional example')}</p><div class="row">${k === 'draft' ? link('announcement-edit', text('Modifier', 'Edit'), 'outline') : k === 'pending' ? button(text('Annuler la soumission', 'Cancel submission'), 'cancel-submission', 'outline') : link('statistics', text('Voir les statistiques', 'View statistics'), 'outline')}</div></div>${badge(t(k))}</article>`).join('')}</div>`;
}
function announcementForm() {
    return `${head()}<div class="narrow">${field(text('Titre de l’annonce', 'Announcement title'), text('Des carnets pour tes journées', 'Journals for your days'))}${area(text('Texte de l’annonce', 'Announcement text'), text('Découvre nos carnets pour conserver les souvenirs de tes journées entre amis.', 'Discover our journals for keeping memories of days with friends.'), 'announcement-content')}${field(text('Lien HTTPS', 'HTTPS link'), 'https://example.test/carnets', 'url', 'announcement-url')}<p class="notice">${text('Après soumission, cette version est figée. L’approbation lance immédiatement l’envoi.', 'After submission, this version is frozen. Approval starts delivery immediately.')}</p><div class="row">${button(t('save'))}${button(t('submit'), 'submit-announcement', 'outline')}</div></div>`;
}
function stats() {
    return `${head(text('Des volumes agrégés, sans identité de destinataire. Données fictives.', 'Aggregated volumes, without recipient identities. Fictional data.'))}<div class="statistics">${[
        [t('sent'), '240'],
        [text('Lectures', 'Reads'), '132'],
        [text('Clics uniques', 'Unique clicks'), '28'],
    ]
        .map(
            ([l, v]) =>
                `<div class="stat"><span>${l}</span><strong>${v}</strong></div>`,
        )
        .join(
            '',
        )}</div><section class="section"><h2>${text('Par annonce', 'By announcement')}</h2><div class="cards-mobile panel"><h3>${text('Des carnets pour tes journées', 'Journals for your days')}</h3><p>${text('240 livraisons · 132 lectures · 28 clics uniques · 35 clics au total · 6 retraits', '240 deliveries · 132 reads · 28 unique clicks · 35 total clicks · 6 dismissals')}</p></div><div class="table-desktop"><table><caption>${text('Résultats agrégés fictifs', 'Fictional aggregate results')}</caption><thead><tr>${[text('Annonce', 'Announcement'), text('Livraisons', 'Deliveries'), text('Lectures', 'Reads'), text('Clics uniques / total', 'Unique / total clicks'), text('Retraits', 'Dismissals')].map((k) => `<th>${k}</th>`).join('')}</tr></thead><tbody><tr><td>${text('Des carnets pour tes journées', 'Journals for your days')}</td><td>240</td><td>132</td><td>28 / 35</td><td>6</td></tr></tbody></table></div>${current.group === 'admin' ? `<div class="section row">${badge(text('2 livraisons à reprendre', '2 deliveries to retry'))}${button(text('Reprendre le traitement', 'Resume processing'), 'retry-dispatch', 'outline')}</div>` : ''}</section>`;
}
function members() {
    const names = ['Camille', t('longName'), 'Alex'];
    const actions = () =>
        `<div class="row">${button(text('Rôles', 'Roles'), 'roles', 'outline')}${button(t('chats'), 'assistance', 'outline')}${button(text('Bannir', 'Ban'), 'ban', 'outline')}${button(t('delete'), 'delete-member', 'outline')}</div>`;

    return `${head(text('Les actions sensibles restent distinctes et confirmées.', 'Sensitive actions remain distinct and require confirmation.'))}<form class="row" data-form="search"><div class="grow" style="flex:1">${field(text('Nom ou adresse e-mail', 'Name or email address'), '', 'search', 'member-search')}</div>${button(t('search'), 'search')}</form><div class="cards-mobile">${names.map((name, i) => `<article class="panel section"><div class="row">${avatar()}<div><h2>${esc(name)}</h2><small>personne${i + 1}@example.test</small></div></div><div class="row section">${badge(t('active'))}${badge(text('Membre', 'Member'))}</div><p class="section">${text('Likes 3 / 2 · Refus 1 / 1 · Univers croisés 2 · Messages 8 · Blocages 0 / 0', 'Likes 3 / 2 · Passes 1 / 1 · Crossed worlds 2 · Messages 8 · Blocks 0 / 0')}</p>${actions()}</article>`).join('')}</div><div class="table-desktop"><table><caption>${text('Comptes · compteurs envoyés / reçus fictifs', 'Accounts · fictional sent / received counts')}</caption><thead><tr>${[t('members'), text('Compte', 'Account'), text('Activité', 'Activity'), text('Actions', 'Actions')].map((h) => `<th>${h}</th>`).join('')}</tr></thead><tbody>${names.map((name, i) => `<tr><td><strong>${esc(name)}</strong><p>personne${i + 1}@example.test</p></td><td>${badge(t('active'))}<p>${text('Membre · visible', 'Member · visible')}</p></td><td>${text('Likes 3 / 2 · Refus 1 / 1', 'Likes 3 / 2 · Passes 1 / 1')}<p>${text('Univers croisés 2 · Messages 8', 'Crossed worlds 2 · Messages 8')}</p><small>${text('Blocages 0 / 0', 'Blocks 0 / 0')}</small></td><td>${actions()}</td></tr>`).join('')}</tbody></table></div>`;
}
function dashboard() {
    const onboarding = current.id === 'onboarding-admin';

    return `${head()}<div class="statistics">${[
        [text('Profils complétés', 'Completed profiles'), '24'],
        [text('Univers croisés', 'Crossed worlds'), '18'],
        [text('Tutoriels terminés', 'Completed tutorials'), '21'],
    ]
        .map(
            ([l, v]) =>
                `<div class="stat"><span>${l}</span><strong>${v}</strong><small>${text('Données fictives', 'Fictional data')}</small></div>`,
        )
        .join('')}</div>${
        onboarding
            ? `<section class="section"><h2>${text('Deux avatars pour le tutoriel', 'Two tutorial avatars')}</h2><div class="row section">${avatar('CA')}${avatar('ML', true)}</div><p class="muted section">${text('Choisir deux avatars actifs et distincts.', 'Choose two distinct active avatars.')}</p>${button(t('save'))}</section>`
            : `<section class="section"><h2>${text('À examiner', 'To review')}</h2><div class="list">${[
                  ['reports', text('2 signalements ouverts', '2 open reports')],
                  [
                      'partner-review',
                      text(
                          'Une fiche partenaire en attente',
                          'One partner profile awaiting review',
                      ),
                  ],
                  [
                      'announcement-review',
                      text(
                          'Une annonce en attente',
                          'One announcement awaiting review',
                      ),
                  ],
              ]
                  .map(
                      ([id, l]) =>
                          `<div class="list-item"><div class="grow">${l}</div>${link(id, text('Examiner', 'Review'), 'outline')}</div>`,
                  )
                  .join('')}</div></section>`
    }`;
}
function catalog() {
    const avatars = current.id === 'avatars';

    return `${head()}${avatars ? '' : field(text('Limite d’univers favoris par profil', 'Favourite world limit per profile'), '5', 'number', 'interest-limit', text('Entre 1 et 100', 'Between 1 and 100'))}${button(text(avatars ? 'Créer un avatar' : 'Créer un univers favori', avatars ? 'Create avatar' : 'Create favourite world'), 'catalog-edit')}<div class="list section">${['music', 'shows', 'food'].map((k, i) => `<article class="list-item">${avatars ? avatar(['CA', 'ML', 'AJ'][i]) : icon('sparkle')}<div class="grow"><strong>${avatars ? text('Avatar du catalogue ', 'Catalogue avatar ') + (i + 1) : t(k)}</strong><small>${i === 2 ? t('archived') : t('active')}</small><div class="row">${button(text('Modifier', 'Edit'), 'catalog-edit', 'outline')}${button(text(i === 2 ? 'Réactiver' : 'Archiver', i === 2 ? 'Reactivate' : 'Archive'), i === 2 ? 'reactivate' : 'archive', 'outline')}${button(text('Monter', 'Move up'), 'simulate', 'quiet')}${button(text('Descendre', 'Move down'), 'simulate', 'quiet')}${button(t('delete'), 'delete-catalog', 'quiet')}</div></div></article>`).join('')}</div>`;
}
function seasons() {
    return `${head(text('L’activation manuelle reste prioritaire jusqu’à sa désactivation.', 'Manual activation takes priority until explicitly disabled.'))}<div class="form-columns">${['halloween', 'christmas'].map((k) => `<section class="panel"><h2>${icon(k === 'halloween' ? 'ghost' : 'snow')} ${t(k)}</h2>${field(text('Début', 'Start'), k === 'halloween' ? '2026-10-01' : '2026-12-01', 'date', k + '-start')}${field(text('Fin', 'End'), k === 'halloween' ? '2026-11-02' : '2027-01-06', 'date', k + '-end')}<div class="row">${button(t('save'))}${button(text('Activer maintenant', 'Activate now'), 'season:' + k, 'outline')}</div></section>`).join('')}</div><div class="section">${button(text('Désactiver l’activation manuelle', 'Deactivate manual override'), 'season:standard', 'outline')}</div>`;
}
function moderation() {
    const announcement = current.id === 'announcement-review';

    return `${head()}<div class="tabs">${button(t('pending'), 'simulate', 'selected')}${button(text('Historique', 'History'), 'simulate', 'outline')}</div><section class="panel"><div class="row spread"><h2>${text('Les Carnets de Visite', 'Visit Journals')}</h2>${badge(t('pending'))}</div><div class="form-columns section"><div><h3>Français</h3><p>Un atelier indépendant pour garder les souvenirs de tes journées entre amis.</p></div><div><h3>English</h3><p>An independent workshop for preserving memories of days with friends.</p></div></div>${announcement ? `<p class="notice section">${text('Approuver lance immédiatement l’envoi aux membres éligibles. Aucun destinataire individuel n’est exposé.', 'Approval immediately starts delivery to eligible members. No individual recipients are exposed.')}</p>` : ''}${area(t('reason'), '', 'moderation-reason')}<div class="row">${button(t('approve'), 'approve-' + (announcement ? 'announcement' : 'partner'))}${button(t('reject'), 'reject-moderation', 'outline')}</div></section>`;
}
function reports() {
    return `${head()}<div class="tabs">${button(text('Ouverts', 'Open'), 'simulate', 'selected')}${button(text('Clôturés', 'Closed'), 'simulate', 'outline')}</div><div class="list"><article class="list-item">${icon('flag')}<div class="grow"><strong>${text('Échange signalé par Camille', 'Conversation reported by Camille')}</strong><p>${text('Comportement inapproprié · 7 octobre 2026', 'Inappropriate behaviour · 7 October 2026')}</p></div>${link('report-detail', text('Examiner', 'Review'), 'outline')}</article></div>`;
}
function reportDetail() {
    return `${head(text('Consultation auditée. Les messages originaux restent en lecture seule.', 'Audited viewing. Original messages remain read only.'))}<p class="notice">${text('Accès administratif aux messages anciens et futurs de cet échange signalé. Aucun changement du statut de lecture des participants.', 'Administrative access to past and future messages in this reported conversation. Participant read states remain unchanged.')}</p><div class="chat-layout"><div class="timeline"><article class="message"><small>Camille · 10:42</small><div class="bubble">${text('Message fictif pour montrer la revue, sans contenu privé réel.', 'Fictional message illustrating review, without real private content.')}</div></article><article class="message mine"><small>Alex · 10:44</small><div class="bubble">${text('Deuxième message fictif.', 'Second fictional message.')}</div></article></div><div class="row">${button(text('Messages précédents', 'Previous messages'), 'simulate', 'outline')}${badge('1 / 2')}${button(text('Messages suivants', 'Next messages'), 'simulate', 'outline')}</div><section class="section">${area(t('reason'), '', 'report-decision', text('1 000 caractères maximum', 'Up to 1,000 characters'))}<p class="notice">${text('Bannir révoque les accès sans expiration et conserve les données. La levée ne restaure pas les états précédents. Un administrateur ne peut pas être banni.', 'Banning revokes access without expiry and retains data. Unbanning does not restore previous states. Administrators cannot be banned.')}</p><div class="row">${button(text('Clôturer le signalement', 'Close report'), 'close-report')}${button(text('Bannir Alex', 'Ban Alex'), 'ban-from-report', 'outline')}</div>${button(text('Vue compte banni : lever le bannissement', 'Banned account view: lift ban'), 'unban', 'quiet')}</section></div>`;
}
function reportForm() {
    const reasons = text(
        [
            'Comportement inapproprié',
            'Harcèlement',
            'Discrimination',
            'Contenu sexuel',
            'Spam ou publicité',
            'Autre',
        ],
        [
            'Inappropriate behaviour',
            'Harassment',
            'Discrimination',
            'Sexual content',
            'Spam or advertising',
            'Other',
        ],
    );

    return `<p class="notice">${text('Les administrateurs pourront consulter toute la conversation, y compris les messages anciens et ultérieurs.', 'Administrators will be able to view the entire conversation, including past and future messages.')}</p><div class="field"><label for="report-reason">${text('Motif du signalement', 'Report reason')}</label><select id="report-reason">${reasons.map((r) => `<option>${r}</option>`).join('')}</select></div>${area(text('Précision facultative — 1 000 caractères maximum', 'Optional details — up to 1,000 characters'), '', 'report-details')}<fieldset><legend>${text('Bloquer aussi cette personne ?', 'Also block this person?')}</legend><label class="check"><input type="radio" name="block-report" checked><span>${text('Oui', 'Yes')}</span></label><label class="check"><input type="radio" name="block-report"><span>${text('Non', 'No')}</span></label></fieldset><p class="muted">${text('Pour un administrateur, ce choix de blocage est absent.', 'For an administrator, the blocking option is absent.')}</p>${button(text('Confirmer le signalement', 'Confirm report'), 'send-report')}`;
}
function components() {
    return `${head()}<section class="section"><h2>${text('Palette sémantique', 'Semantic palette')}</h2><div class="swatches">${['background', 'foreground', 'card', 'primary', 'secondary', 'muted', 'destructive'].map((k) => `<div class="swatch"><i style="background:var(--${k})"></i><span>${k}</span></div>`).join('')}</div></section><section class="section"><h2>${text('Actions et états', 'Actions and states')}</h2><div class="row">${button(t('discover'))}${button(t('pass'), 'simulate', 'outline')}<button disabled class="btn">${t('disabled')}</button><button class="btn" disabled aria-busy="true">${t('loadMessage')}</button><button aria-pressed="true" class="btn secondary" data-action="toggle-selected">${icon('check')}${t('selected')}</button></div><div class="section">${field(t('email'), 'camille@', 'email', 'error-email')}<p class="error" id="error-email-help">${text('Cette adresse e-mail est incomplète. Vérifie-la avant de continuer.', 'This email address is incomplete. Check it before continuing.')}</p></div></section><section class="section"><h2>${t('unread')}</h2><article class="list-item unread">${avatar()}<div class="grow"><strong>Camille</strong><p>${text('Un nouveau message', 'A new message')}</p></div>${badge('1')}</article></section><section class="section"><h2>${t('success')}</h2><p class="toast-example">${t('notify')}</p></section><section class="section"><h2>${t('empty')}</h2>${emptyView()}</section><section class="section"><h2>${t('loading')}</h2><div class="skeleton" aria-hidden="true"></div><p role="status">${t('loadMessage')}</p></section><section class="section"><h2>${text('Confirmation sensible', 'Sensitive confirmation')}</h2>${button(text('Supprimer le compte de démonstration', 'Delete demo account'), 'delete-account', 'outline')}</section>`;
}
function workspace() {
    return `${head()}<p class="notice">${text('Compte fictif cumulant les rôles membre, partenaire et administrateur. Les espaces non autorisés ne seront jamais proposés en production.', 'Fictional account with member, partner and administrator roles. Unauthorised workspaces will never be offered in production.')}</p><div class="list">${[
        ['explore', text('Espace membre', 'Member workspace')],
        ['partner-profile', text('Espace partenaire', 'Partner workspace')],
        ['dashboard', text('Administration', 'Administration')],
    ]
        .map(
            ([id, l]) =>
                `<div class="list-item"><div class="grow"><strong>${l}</strong><small>${text('Aucun rôle modifié ou mémorisé par le changement d’espace.', 'Switching workspace does not change or persist roles.')}</small></div>${link(id, t('continue'), 'outline')}</div>`,
        )
        .join('')}</div>`;
}
function pwa() {
    return `${head()}<section class="panel"><h2>${text('Une nouvelle version est disponible', 'A new version is available')}</h2><p>${text('Recharge l’application quand tu es prêt.', 'Reload the app when you are ready.')}</p><div class="row section">${button(text('Mettre à jour', 'Update'), 'simulate')}${button(text('Plus tard', 'Later'), 'simulate', 'outline')}</div></section><section class="section"><h2>${text('Installer l’application', 'Install the app')}</h2><p>${text('Sur iPhone : Partager, puis Sur l’écran d’accueil. Sur les navigateurs compatibles, utilise Installer.', 'On iPhone: Share, then Add to Home Screen. In compatible browsers, use Install.')}</p>${button(text('Installer', 'Install'), 'simulate', 'outline')}</section><section class="section"><h2>${text('Tu es hors ligne', 'You are offline')}</h2><p>${text('Retrouve une connexion, puis réessaie. Aucun message privé n’est conservé dans le cache de cette maquette.', 'Reconnect, then try again. No private messages are stored in this mockup’s cache.')}</p>${button(t('retry'), 'retry', 'outline')}</section><section class="section"><h2>${text('Notifications sur cet appareil', 'Notifications on this device')}</h2><p>${text('Tu peux refuser et changer d’avis dans les réglages.', 'You can decline and change your mind in settings.')}</p>${button(text('Activer', 'Enable'), 'push')}${button(text('Pas maintenant', 'Not now'), 'simulate', 'outline')}</section>`;
}
function cookies() {
    return `${head()}<div class="narrow"><p>${text('Les cookies nécessaires permettent le fonctionnement du service. La mesure d’audience reste désactivée tant que tu ne l’acceptes pas. Ton choix est conservé six mois.', 'Necessary cookies enable the service to work. Audience measurement stays disabled until you accept it. Your choice is retained for six months.')}</p><div class="row section">${button(text('Refuser la mesure d’audience', 'Decline analytics'), 'cookie-decline', 'outline')}${button(text('Accepter la mesure d’audience', 'Accept analytics'), 'cookie-accept')}</div>${button(text('Retirer mon accord', 'Withdraw consent'), 'cookie-decline', 'quiet')}</div>`;
}
function tutorial() {
    let id = current.id;
    const steps = [
        'tutorial-pass',
        'tutorial-like',
        'tutorial-match',
        'tutorial-chat',
        'install',
    ];
    tutorialStep = Math.max(0, steps.indexOf(id));

    if (id === 'install') {
        return pwa();
    }

    if (id === 'tutorial-match') {
        return match();
    }

    if (id === 'tutorial-chat') {
        return chat();
    }

    return `${head(text('Démonstration : aucune donnée sociale ne sera créée.', 'Demo: no social data will be created.'))}<div class="narrow"><div class="step-line">${steps.map((_, i) => `<span class="${i <= tutorialStep ? 'done' : ''}"></span>`).join('')}</div>${profileCard()}<div class="decision-actions">${button(t('pass'), 'tutorial-pass', 'outline')}${button(t('discover'), 'tutorial-like')}</div><p class="notice">${id === 'tutorial-pass' ? text('Commence par Passer pour essayer le geste de refus.', 'Start with Pass to try the rejection gesture.') : text('Choisis Découvrir pour exprimer ton envie d’échanger.', 'Choose Discover to express your wish to chat.')}</p></div>`;
}
function content() {
    if (state === 'loading') {
        return `${head()}<div class="narrow" aria-busy="true"><div class="skeleton"></div><div class="skeleton line"></div><p role="status">${t('loadMessage')}</p>${button(t('continue'), 'retry', 'outline')}</div>`;
    }

    if (state === 'empty' && !['chat'].includes(current.kind)) {
        return head() + emptyView();
    }

    if (state === 'disabled') {
        return (
            head() +
            `<div class="empty">${icon('shield')}<h2>${text('Ce contenu n’est plus disponible', 'This content is no longer available')}</h2><p>${text('La disponibilité et les autorisations sont revérifiées côté serveur.', 'Availability and permissions are rechecked on the server.')}</p>${link(current.group === 'admin' ? 'dashboard' : 'explore', t('back'), 'outline')}</div>`
        );
    }

    const renderers = {
        home,
        read: reading,
        empty: () =>
            head() +
            `<div class="empty"><h2>404</h2><p>${text('Cette page n’existe pas ou n’est plus disponible.', 'This page does not exist or is no longer available.')}</p>${link('home', text('Retour à l’accueil', 'Back to home'))}</div>`,
        auth,
        'profile-form': profileForm,
        tutorial,
        discovery,
        profile,
        people,
        match,
        chat,
        events,
        event: () => events(),
        'event-form': () => events(),
        notifications,
        settings,
        partner,
        announcements,
        'announcement-form': announcementForm,
        statistics: stats,
        members,
        dashboard,
        catalog,
        seasons,
        moderation,
        reports,
        report: reportDetail,
        'report-form': () =>
            head() + `<div class="narrow">${reportForm()}</div>`,
        components,
        workspace,
        pwa,
        cookies,
    };

    return (
        (['auth', 'chat'].includes(current.kind) ? '' : stateNotice()) +
        renderers[current.kind]()
    );
}
function controls() {
    const groups = {
        public: text('Public', 'Public'),
        auth: text('Compte et premiers pas', 'Account and onboarding'),
        member: text('Membre', 'Member'),
        partner: t('partners'),
        admin: text('Administration', 'Administration'),
        shared: text('Transversal', 'Shared'),
    };

    return `<details><summary>${t('prototype')}</summary><div class="review-head"><span>${t('prototype')}</span><a href="../README.md">${text('Dossier de conception', 'Design dossier')}</a></div><div class="review-controls"><label class="screen-control">${t('screen')}<select id="screen-selector">${Object.entries(
        groups,
    )
        .map(
            ([g, l]) =>
                `<optgroup label="${l}">${screens
                    .filter((s) => s.group === g)
                    .map(
                        (s) =>
                            `<option value="${s.id}" ${s.id === current.id ? 'selected' : ''}>${esc(s[lang])}</option>`,
                    )
                    .join('')}</optgroup>`,
        )
        .join(
            '',
        )}</select></label><label>${t('theme')}<select id="theme-selector">${['light', 'dark'].map((k) => `<option value="${k}" ${k === theme ? 'selected' : ''}>${t(k)}</option>`).join('')}</select></label><label>${t('season')}<select id="season-selector">${['standard', 'halloween', 'christmas'].map((k) => `<option value="${k}" ${k === season ? 'selected' : ''}>${t(k)}</option>`).join('')}</select></label><label>${t('state')}<select id="state-selector">${['normal', 'empty', 'loading', 'error', 'success', 'disabled', 'readonly', 'selected', 'unread'].map((k) => `<option value="${k}" ${k === state ? 'selected' : ''}>${t(k)}</option>`).join('')}</select></label><label>${text('Langue', 'Language')}<select id="language-selector"><option value="fr" ${lang === 'fr' ? 'selected' : ''}>Français</option><option value="en" ${lang === 'en' ? 'selected' : ''}>English</option></select></label></div><p class="source-note">${esc(current.source)} · ${esc(current.route)}</p></details>`;
}
function render(focus = false) {
    document.documentElement.lang = lang;
    document.documentElement.dataset.theme = theme;
    document.documentElement.dataset.season = season;
    document.title = `DLP Friends · ${current[lang]} · 259`;
    $('#review').innerHTML = controls();
    const passedPanel = current.id === 'passed-profile';
    const eventPanel = [
        'event',
        'event-create',
        'event-edit',
        'participants',
        'participant-profile',
        'requests',
        'group-chat',
    ].includes(current.id);
    let body;

    if (eventPanel) {
        const selected = current;
        current = screens.find((s) => s.id === 'events');
        body = events();
        current = selected;
    } else if (passedPanel) {
        const selected = current;
        current = screens.find((s) => s.id === 'passed');
        body = people();
        current = selected;
    } else {
        body = content();
    }

    $('#app').innerHTML =
        header() +
        `<main id="content" class="frame ${current.group === 'admin' ? 'admin-layout' : ''}">${body}</main>` +
        navigation();

    if (state === 'selected') {
        $('#app .btn')?.setAttribute('aria-pressed', 'true');
    }

    if (current.id === 'components' && $('#error-email')) {
        $('#error-email').setAttribute('aria-invalid', 'true');
        $('#error-email').setAttribute('aria-describedby', 'error-email-help');
    }

    if (focus) {
        $('#page-title')?.focus({ preventScroll: true });
    }

    if (passedPanel) {
        openPanel(
            current[lang],
            `<div class="dialog-profile">${profileCard()}<div class="row section">${link('passed', t('back'), 'outline')}${button(t('discover'), 'discover')}</div></div>`,
        );
    }

    if (eventPanel) {
        let panelBody =
            current.id === 'event'
                ? eventDetail()
                : current.kind === 'event-form'
                  ? eventForm()
                  : current.id === 'participants'
                    ? participantsPanel()
                    : current.id === 'participant-profile'
                      ? `<div class="dialog-profile">${profileCard()}<div class="row section">${button(t('back'), 'participants', 'outline')}${button(t('discover'), 'discover')}</div></div>`
                      : content();
        openPanel(current[lang], panelBody);
    }
}
function participantsPanel() {
    return `<div class="list">${['Camille', 'Morgan', t('you')].map((n, i) => `<div class="list-item ${i === 1 ? 'blocked' : ''}">${avatar(['CA', 'MO', 'ML'][i])}<div class="grow"><strong>${n}</strong><div class="row">${i === 1 ? badge(t('blocked')) + button(t('unblock'), 'unblock', 'outline') : i === 2 ? '' : button(text('Profil', 'Profile'), 'open-profile', 'outline') + button(t('discover'), 'discover')}</div></div></div>`).join('')}</div><div class="section">${button(t('back'), 'event-detail', 'outline')}</div>`;
}
function go(id, push = true) {
    const next = screens.find((s) => s.id === id);

    if (!next) {
        return;
    }

    closePanel();
    current = next;
    state = 'normal';

    if (push) {
        history.pushState({}, '', url(id));
    }

    render(true);
    window.scrollTo(0, 0);
}
function toast(message = t('notify')) {
    $('#toast').textContent = message;
    $('#toast').style.display = 'block';
    clearTimeout(window.toastTimer);
    window.toastTimer = setTimeout(
        () => ($('#toast').style.display = 'none'),
        4200,
    );
}
function openPanel(title, body) {
    const d = $('#panel');
    pendingFocus = document.activeElement;
    d.innerHTML = `<button class="btn quiet icon-button close" data-action="close" aria-label="${t('close')}">${icon('close')}</button><h2 id="panel-title">${esc(title)}</h2>${body}`;

    if (!d.open) {
        d.showModal();
    }
}
function closePanel() {
    const d = $('#panel');

    if (d.open) {
        d.close();
    }
}
function confirmation(title, description, action, danger = false, extra = '') {
    openPanel(
        title,
        `<p>${esc(description)}</p>${extra}<div class="dialog-actions">${button(t('cancel'), 'close', 'outline')}${button(title, action, danger ? 'danger' : '')}</div>`,
    );
}
function decision(action) {
    const reasons = {
        'delete-account': text(
            'Ton accès cesse immédiatement. La purge intervient après 30 jours. Cette demande est définitive.',
            'Your access ends immediately. Purging happens after 30 days. This request is permanent.',
        ),
        'delete-member': text(
            'Le compte membre sera supprimé immédiatement. Un administrateur ne peut pas être supprimé.',
            'The member account will be deleted immediately. An administrator cannot be deleted.',
        ),
        ban: text(
            'Les accès seront révoqués sans expiration. Les données restent conservées. La levée ne rétablit pas les anciens états.',
            'Access will be revoked without expiry. Data remains retained. Unbanning will not restore previous states.',
        ),
        block: text(
            'Les suggestions et l’échange deviendront inaccessibles aux deux membres, sans notification explicite.',
            'Suggestions and the conversation will become unavailable to both members, without an explicit notification.',
        ),
        'cancel-event': text(
            'L’événement sera annulé et les participants seront notifiés. La discussion devient immédiatement consultable uniquement.',
            'The event will be cancelled and participants notified. The discussion immediately becomes read only.',
        ),
        'reject-request': text(
            'Cette demande sera refusée. La personne recevra une notification de la décision.',
            'This request will be rejected. The person will receive a decision notification.',
        ),
        'dismiss-announcement': text(
            'Cette annonce sera retirée de ton centre de notifications.',
            'This announcement will be removed from your notification centre.',
        ),
    };
    const names = {
        'delete-account': text('Confirmer la suppression', 'Confirm deletion'),
        'delete-member': text('Supprimer ce membre', 'Delete this member'),
        ban: text('Bannir ce compte', 'Ban this account'),
        block: t('block'),
        'cancel-event': text('Annuler l’événement', 'Cancel event'),
        'reject-request': t('reject'),
        'dismiss-announcement': text(
            'Retirer l’annonce',
            'Dismiss announcement',
        ),
    };
    let extra = '';

    if (action === 'delete-account') {
        extra =
            field(
                text(
                    'Mot de passe actuel — compte avec mot de passe',
                    'Current password — password account',
                ),
                '',
                'password',
                'delete-password',
            ) +
            check(
                text(
                    'Compte exclusivement social : je confirme les conséquences irréversibles.',
                    'Social-only account: I confirm the irreversible consequences.',
                ),
                'social-delete',
            ) +
            `<small>${text('Deux variantes de revue ; le serveur en présente une seule selon le compte.', 'Two review variants; the server presents only one according to the account.')}</small>`;
    }

    if (action === 'ban') {
        extra = area(t('reason'), '', 'ban-reason');
    }

    confirmation(
        names[action],
        reasons[action],
        'confirm:' + action,
        true,
        extra,
    );
}
document.addEventListener('click', (e) => {
    const screen = e.target.closest('[data-screen]');

    if (screen) {
        e.preventDefault();
        go(screen.dataset.screen);

        return;
    }

    const control = e.target.closest('[data-action]');

    if (!control || control.disabled) {
        return;
    }

    e.preventDefault();
    const action = control.dataset.action;

    if (action === 'close' && current.id === 'passed-profile') {
        go('passed');

        return;
    }

    if (action === 'close') {
        if (
            [
                'event',
                'event-create',
                'event-edit',
                'participants',
                'participant-profile',
                'requests',
                'group-chat',
            ].includes(current.id)
        ) {
            go(
                current.id === 'participant-profile'
                    ? 'participants'
                    : current.id === 'participants'
                      ? 'event'
                      : 'events',
            );
        } else {
            closePanel();
        }

        return;
    }

    if (action === 'retry') {
        state = 'normal';
        render();

        return;
    }

    if (action === 'simulate' || action === 'save-event') {
        closePanel();
        toast();

        return;
    }

    if (action.startsWith('confirm:')) {
        closePanel();
        toast();

        if (action === 'confirm:cancel-event') {
            state = 'readonly';
            go('group-chat');
            state = 'readonly';
            render();
        }

        return;
    }

    if (
        [
            'delete-account',
            'delete-member',
            'ban',
            'block',
            'cancel-event',
            'reject-request',
            'dismiss-announcement',
        ].includes(action)
    ) {
        decision(action);

        return;
    }

    if (action === 'discover') {
        openPanel(
            text('Vos univers se croisent', 'Your worlds cross'),
            match(),
        );

        return;
    }

    if (action === 'pass') {
        toast(
            text(
                'Profil passé. Aucun like créé.',
                'Profile passed. No like created.',
            ),
        );

        return;
    }

    if (action === 'open-chat') {
        go('chat');

        return;
    }

    if (
        action === 'open-profile' &&
        ['participants', 'participant-profile'].includes(current.id)
    ) {
        go('participant-profile');

        return;
    }

    if (action === 'open-profile' && current.id === 'passed') {
        go('passed-profile');

        return;
    }

    if (action === 'open-profile') {
        openPanel(
            text('Profil de Camille', 'Camille’s profile'),
            `<div class="dialog-profile">${profileCard()}<div class="row section">${button(t('back'), current.id === 'participants' ? 'participants' : 'close', 'outline')}${button(t('discover'), 'discover')}</div></div>`,
        );

        return;
    }

    if (action === 'workspace') {
        openPanel(t('spaces'), workspace());

        return;
    }

    if (action === 'catalogues' || action === 'partners-menu') {
        const ids =
            action === 'catalogues'
                ? [
                      'reports',
                      'interests',
                      'avatars',
                      'onboarding-admin',
                      'seasons',
                  ]
                : ['partner-review', 'announcement-review', 'admin-statistics'];
        openPanel(
            action === 'catalogues' ? t('catalogues') : t('partners'),
            `<div class="stack">${ids.map((id) => link(id, screens.find((s) => s.id === id)[lang], 'outline full')).join('')}</div>`,
        );

        return;
    }

    if (action === 'report') {
        openPanel(t('report'), reportForm());

        return;
    }

    if (action === 'send-report') {
        closePanel();
        toast(
            text(
                'Signalement simulé avec blocage selon ton choix.',
                'Simulated report with blocking according to your choice.',
            ),
        );

        return;
    }

    if (action === 'event-detail') {
        go('event');

        return;
    }

    if (action === 'event-accepted') {
        acceptedEvent = true;
        go('event');

        return;
    }

    if (action === 'join-event') {
        confirmation(
            t('join'),
            text(
                'Ta demande attendra la décision de l’organisateur. Le lieu précis et les participants resteront privés jusque-là.',
                'Your request will await the organiser’s decision. The exact location and participants remain private until then.',
            ),
            'confirm:join',
        );

        return;
    }

    if (action === 'participants') {
        go('participants');

        return;
    }

    if (action === 'unblock') {
        confirmation(
            t('unblock'),
            text(
                'Le blocage sera retiré. Les autres règles de disponibilité restent applicables.',
                'The block will be removed. Other availability rules still apply.',
            ),
            'confirm:unblock',
        );

        return;
    }

    if (action === 'profile-next') {
        if (profileStep < 3) {
            profileStep++;
            render();
        } else {
            profileStep = 0;
            go(current.id === 'create-profile' ? 'tutorial-pass' : 'profile');
            toast();
        }

        return;
    }

    if (action === 'profile-back') {
        profileStep = Math.max(0, profileStep - 1);
        render();

        return;
    }

    if (action === 'avatar-select') {
        document
            .querySelectorAll('[data-action="avatar-select"]')
            .forEach((b) => {
                b.classList.replace('secondary', 'outline');
                b.setAttribute('aria-pressed', 'false');
            });
        control.classList.replace('outline', 'secondary');
        control.setAttribute('aria-pressed', 'true');

        return;
    }

    if (action === 'tutorial-pass') {
        if (current.id === 'tutorial-pass') {
            go('tutorial-like');
        } else {
            toast(
                text(
                    'Pour cette étape, choisis Découvrir.',
                    'For this step, choose Discover.',
                ),
            );
        }

        return;
    }

    if (action === 'tutorial-like') {
        if (current.id === 'tutorial-like') {
            go('tutorial-match');
        } else {
            toast(text('Commence par Passer.', 'Start with Pass.'));
        }

        return;
    }

    if (action.startsWith('starter:')) {
        $('#message').value = text(
            'Quel est ton univers favori ?',
            'What is your favourite world?',
        );
        $('#message').dispatchEvent(new Event('input', { bubbles: true }));
        $('#message').focus();

        return;
    }

    if (action === 'like-message') {
        control.setAttribute(
            'aria-pressed',
            control.getAttribute('aria-pressed') !== 'true',
        );
        control.classList.toggle('secondary');

        return;
    }

    if (action === 'roles') {
        openPanel(
            text('Modifier les rôles', 'Edit roles'),
            check(text('Membre', 'Member'), 'role-user', true) +
                check(text('Partenaire', 'Partner'), 'role-partner') +
                `<p class="muted">${text('Administrateur : attribution uniquement par console. Toute modification est auditée.', 'Administrator: console assignment only. Every change is audited.')}</p><div class="section">${button(text('Confirmer les rôles', 'Confirm roles'), 'confirm:roles')}</div>`,
        );

        return;
    }

    if (action === 'assistance') {
        confirmation(
            text(
                'Créer ou rouvrir un échange',
                'Create or reopen a conversation',
            ),
            text(
                'La paire reste unique. Aucun swipe artificiel ne sera créé.',
                'The pair stays unique. No artificial swipe will be created.',
            ),
            'discover',
        );

        return;
    }

    if (action === 'ban-from-report') {
        const reason = $('#report-decision');

        if (!reason.value.trim()) {
            reason.setAttribute('aria-invalid', 'true');
            toast(
                text(
                    'Indique un motif avant de bannir.',
                    'Enter a reason before banning.',
                ),
            );
            reason.focus();
        } else {
            toast(
                text(
                    'Bannissement simulé, sans expiration.',
                    'Simulated ban, without expiry.',
                ),
            );
        }

        return;
    }

    if (action === 'close-report') {
        const reason = $('#report-decision');

        if (!reason.value.trim()) {
            toast(
                text(
                    'Indique un motif de décision.',
                    'Enter a decision reason.',
                ),
            );
            reason.focus();
        } else {
            toast();
        }

        return;
    }

    if (action === 'unban') {
        confirmation(
            text('Lever le bannissement', 'Lift ban'),
            text(
                'Les accès deviennent à nouveau possibles ; les anciens états supprimés ne seront pas restaurés.',
                'Access becomes possible again; previously removed states will not be restored.',
            ),
            'confirm:unban',
        );

        return;
    }

    if (action === 'submit-partner' || action === 'submit-announcement') {
        confirmation(
            t('submit'),
            text(
                'Cette soumission fige une révision. La publication attendra l’approbation administrative.',
                'This submission freezes a revision. Publication awaits administrative approval.',
            ),
            'confirm:submission',
        );

        return;
    }

    if (action === 'approve-announcement' || action === 'approve-partner') {
        confirmation(
            t('approve'),
            action === 'approve-announcement'
                ? text(
                      'L’approbation lance immédiatement l’envoi. Le délai et les destinataires éligibles sont vérifiés côté serveur.',
                      'Approval starts delivery immediately. Delay and eligible recipients are checked on the server.',
                  )
                : text(
                      'Cette révision remplacera la fiche publiée.',
                      'This revision will replace the published profile.',
                  ),
            'confirm:approval',
        );

        return;
    }

    if (action === 'reject-moderation') {
        const reason = $('#moderation-reason');

        if (!reason.value.trim()) {
            toast(
                text('Indique un motif de refus.', 'Enter a rejection reason.'),
            );
            reason.focus();
        } else {
            toast();
        }

        return;
    }

    if (action === 'catalog-edit') {
        openPanel(
            text('Modifier le catalogue', 'Edit catalogue'),
            field(
                text('Nom en français', 'French name'),
                t('music'),
                'text',
                'catalog-fr',
            ) +
                field(
                    text('Nom en anglais', 'English name'),
                    'Music',
                    'text',
                    'catalog-en',
                ) +
                (current.id === 'avatars'
                    ? field(
                          text('Première couleur', 'First colour'),
                          '#7744aa',
                          'color',
                          'color-primary',
                      ) +
                      field(
                          text('Deuxième couleur', 'Second colour'),
                          '#f2c2dc',
                          'color',
                          'color-secondary',
                      )
                    : '') +
                button(t('save'), 'simulate'),
        );

        return;
    }

    if (['archive', 'reactivate', 'delete-catalog'].includes(action)) {
        confirmation(
            text('Confirmer cette modification', 'Confirm this change'),
            text(
                'Les contraintes de sélection et d’historique seront vérifiées côté serveur. Une réactivation ne restaure que les sélections disposant de capacité.',
                'Selection and history constraints will be checked on the server. Reactivation restores only selections with remaining capacity.',
            ),
            'confirm:catalog',
            action === 'delete-catalog',
        );

        return;
    }

    if (action === 'export') {
        toast(
            text(
                'Export simulé. Le prototype ne contient aucune donnée personnelle réelle.',
                'Simulated export. The prototype contains no real personal data.',
            ),
        );

        return;
    }

    if (action === 'push') {
        confirmation(
            text('Activer sur cet appareil', 'Enable on this device'),
            text(
                'Une demande native d’autorisation sera proposée en production. Tu peux refuser.',
                'A native permission request will be offered in production. You can decline.',
            ),
            'confirm:push',
        );

        return;
    }

    if (action === 'setup-2fa') {
        openPanel(
            text('Configurer la vérification', 'Set up verification'),
            `<p>${text('Le QR code et les secrets restent exclusivement produits par le parcours sécurisé existant.', 'QR codes and secrets are generated exclusively by the existing secure flow.')}</p>${field(text('Code de confirmation', 'Confirmation code'), '', 'text', '2fa-code')}${button(t('continue'), 'simulate')}`,
        );

        return;
    }

    if (action === 'recovery') {
        openPanel(
            text('Code de récupération', 'Recovery code'),
            field(
                text('Code de récupération', 'Recovery code'),
                '',
                'text',
                'recovery-code',
            ) + button(t('continue'), 'simulate'),
        );

        return;
    }

    if (action.startsWith('theme:')) {
        theme =
            action.split(':')[1] === 'system'
                ? matchMedia('(prefers-color-scheme: dark)').matches
                    ? 'dark'
                    : 'light'
                : action.split(':')[1];
        render();

        return;
    }

    if (action.startsWith('season:')) {
        season = action.split(':')[1];
        render();
        toast();

        return;
    }

    if (action.startsWith('notifications-')) {
        notificationFilter = action.split('-')[1];
        render();

        return;
    }

    if (action === 'read-all') {
        state = 'normal';
        notificationFilter = 'unread';
        allRead = true;
        render();
        toast(
            text(
                'Notifications marquées comme lues dans cette démonstration.',
                'Notifications marked as read in this demo.',
            ),
        );

        return;
    }

    if (action === 'open-members') {
        go('members');

        return;
    }

    if (action === 'open-announcement') {
        openPanel(
            text('Annonce reçue', 'Received announcement'),
            `<p>${text('Les Carnets de Visite — découvre nos carnets pour tes journées entre amis.', 'Visit Journals — discover journals for your days with friends.')}</p><p class="muted section">${text('Service externe · nouvel onglet en production. Le lien fictif n’est pas ouvert dans cette maquette.', 'External service · new tab in production. The fictional link is not opened in this mockup.')}</p>${button(text('Retirer cette annonce', 'Dismiss this announcement'), 'dismiss-announcement', 'outline')}`,
        );

        return;
    }

    if (action === 'cookie-accept' || action === 'cookie-decline') {
        toast(
            text(
                'Choix simulé. Aucun outil de mesure ne charge dans le prototype.',
                'Simulated choice. No analytics tool loads in the prototype.',
            ),
        );

        return;
    }

    if (
        [
            'retry-dispatch',
            'cancel-submission',
            'remove-device',
            'accept-request',
        ].includes(action)
    ) {
        confirmation(
            text('Confirmer', 'Confirm'),
            text(
                'Cette action sera contrôlée côté serveur et son résultat affiché.',
                'This action will be checked by the server and its result displayed.',
            ),
            'confirm:' + action,
        );

        return;
    }

    if (action === 'toggle-selected') {
        control.setAttribute(
            'aria-pressed',
            control.getAttribute('aria-pressed') !== 'true',
        );

        return;
    }

    if (action === 'search') {
        const query = $('#member-search')?.value.toLowerCase() || '';
        document
            .querySelectorAll('.cards-mobile article, .table-desktop tbody tr')
            .forEach(
                (el) =>
                    (el.hidden = !el.textContent.toLowerCase().includes(query)),
            );

        return;
    }
});
document.addEventListener('change', (e) => {
    const id = e.target.id;

    if (id === 'screen-selector') {
        go(e.target.value);

        return;
    }

    if (id === 'theme-selector') {
        theme = e.target.value;
    }

    if (id === 'season-selector') {
        season = e.target.value;
    }

    if (id === 'state-selector') {
        state = e.target.value;
    }

    if (id === 'language-selector') {
        lang = e.target.value;
    }

    if (
        [
            'theme-selector',
            'season-selector',
            'state-selector',
            'language-selector',
        ].includes(id)
    ) {
        history.replaceState(
            {},
            '',
            `?screen=${current.id}&lang=${lang}&theme=${theme}&season=${season}&state=${state}`,
        );
        render();

        return;
    }

    if (id === 'accept-terms') {
        document.querySelector('[data-consent="accept-terms"]').disabled =
            !e.target.checked;
    }
});
document.addEventListener('input', (e) => {
    if (e.target.id === 'message') {
        $('#message-count').textContent = `${e.target.value.length} / 2 000`;
        $('#send-message').disabled =
            !e.target.value.trim() || ['readonly', 'disabled'].includes(state);
    }
});
document.addEventListener('submit', (e) => {
    const form = e.target.closest('[data-form]');

    if (!form) {
        return;
    }

    e.preventDefault();

    if (form.dataset.form === 'auth') {
        const b = form.querySelector('[data-next]');

        if (b.disabled) {
            return;
        }

        go(b.dataset.next);
    }

    if (form.dataset.form === 'message') {
        const m = $('#message').value.trim();

        if (m && !['readonly', 'disabled'].includes(state)) {
            savedMessages.push(m);

            if (current.id === 'tutorial-chat') {
                go('install');
            } else {
                render();
            }
        }
    }
});
$('#panel').addEventListener('close', () => {
    if (pendingFocus?.isConnected) {
        pendingFocus.focus();
    }
});
$('#panel').addEventListener('cancel', (event) => {
    event.preventDefault();
    $('#panel [data-action="close"]').click();
});
window.addEventListener('popstate', () => {
    const p = new URLSearchParams(location.search);
    lang = p.get('lang') || lang;
    theme = p.get('theme') || theme;
    season = p.get('season') || season;
    state = p.get('state') || 'normal';
    current = screens.find((s) => s.id === p.get('screen')) || current;
    closePanel();
    render(true);
});
render();
