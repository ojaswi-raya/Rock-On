<?php
/**
 * Demo data shaped like the class diagram:
 * User (abstract) → Member | Administrator, Activity, Category, Participation,
 * Rating, Report, Message, Notification.
 * Written to data/rockon.json the first time the app runs (and on "Reset demo data").
 */

const DEMO_PASSWORD = 'rockon-demo-2026';

function seed_categories(): array
{
    return [
        ['id' => 'outdoors', 'name' => 'Outdoors', 'icon' => '🌳'],
        ['id' => 'local', 'name' => 'Local outings', 'icon' => '🧭'],
        ['id' => 'party', 'name' => 'House parties', 'icon' => '🎉'],
        ['id' => 'weird', 'name' => 'Weird & Fun', 'icon' => '🌀'],
    ];
}

function build_seed(): array
{
    // One bcrypt hash shared by every demo account (they all use DEMO_PASSWORD).
    $hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);
    $img = fn($n) => "assets/img/$n";

    // history = released ratings received before this demo (count + mean per criterion).
    $member = function (string $id, string $name, array $extra = []) use ($hash) {
        return array_merge([
            'id' => $id, 'kind' => 'member', 'name' => $name,
            'email' => substr($id, 2) . '@rockon.app', 'passwordHash' => $hash,
            'photo' => null, 'bio' => '', 'interests' => [], 'languages' => ['English'],
            'joinedAt' => '2026-09-01T10:00:00+00:00', 'suspended' => false, 'blocked' => [],
            'history' => ['n' => 0, 'p' => 0, 'r' => 0, 'f' => 0],
        ], $extra);
    };

    $filler = [
        ['u-farouk', 'Farouk'], ['u-mohit', 'Mohit'], ['u-housnia', 'Housnia'], ['u-harish', 'Harish Raj'],
        ['u-mooez', 'Mooez'], ['u-amara', 'Amara'], ['u-leo', 'Leo'], ['u-ines', 'Inès'], ['u-kofi', 'Kofi'],
        ['u-yuki', 'Yuki'], ['u-dara', 'Dara'], ['u-tomas', 'Tomás'], ['u-zara', 'Zara'], ['u-ravi', 'Ravi'],
        ['u-elena', 'Elena'], ['u-bilal', 'Bilal'], ['u-chloe', 'Chloé'], ['u-sipho', 'Sipho'],
        ['u-mei', 'Mei'], ['u-omar', 'Omar'], ['u-noah', 'Noah'], ['u-aya', 'Aya'],
    ];
    $bios = ['Student · outdoors & photography', 'Young professional, new in town', 'Expat who loves live music', 'Always up for a walk'];
    $ints = [['Outdoors'], ['House parties'], ['Local outings'], ['Weird & Fun', 'Outdoors']];

    $users = [
        $member('u-maya', 'Maya Lee', [
            'photo' => $img('av-maya.jpg'), 'bio' => 'New in the city. I love early walks, friendly groups and finding new art.',
            'interests' => ['Outdoors', 'Local outings'], 'joinedAt' => '2026-08-20T10:00:00+00:00',
            'history' => ['n' => 24, 'p' => 4.9, 'r' => 5.0, 'f' => 4.8],
        ]),
        $member('u-ojaswi', 'Ojaswi Rayamajhi', [
            'photo' => $img('av-ojaswi.jpg'), 'bio' => 'Maths and economics student. Happy to join anything with a good view or a good puzzle.',
            'interests' => ['Outdoors', 'Weird & Fun'], 'languages' => ['English', 'Nepali', 'Hindi'],
            'history' => ['n' => 6, 'p' => 4.7, 'r' => 4.9, 'f' => 4.8],
        ]),
        $member('u-smriti', 'Smriti G C', [
            'photo' => $img('av-smriti.jpg'), 'bio' => 'New to the city · enjoys early walks.',
            'interests' => ['Outdoors'], 'languages' => ['English', 'Nepali'], 'history' => ['n' => 9, 'p' => 4.8, 'r' => 4.7, 'f' => 4.6],
        ]),
        $member('u-clauvie', 'Clauvie Belange', [
            'photo' => $img('av-clauvie.jpg'),
            'bio' => 'I moved here for my bachelor’s degree and I’m slowly learning the city, one park and mural at a time. Always up for a relaxed walk, a good conversation or a very questionable drawing challenge.',
            'interests' => ['Outdoors', 'Street art', 'Weird & Fun'], 'languages' => ['English', 'French'],
            'joinedAt' => '2026-09-10T10:00:00+00:00', 'history' => ['n' => 12, 'p' => 4.8, 'r' => 4.7, 'f' => 4.6],
        ]),
        $member('u-nour', 'Nour Abdelghani', [
            'photo' => $img('av-nour.jpg'), 'bio' => 'Street art nerd. I host walking tours of murals most weekends.',
            'interests' => ['Street art', 'Local outings'], 'languages' => ['English', 'Arabic', 'French'],
            'history' => ['n' => 31, 'p' => 4.6, 'r' => 4.9, 'f' => 4.9],
        ]),
        $member('u-mike', 'Mike', ['photo' => $img('av-mike.jpg'), 'bio' => 'Climber, cook, occasional karaoke menace.', 'interests' => ['Outdoors', 'House parties'], 'history' => ['n' => 5, 'p' => 4.2, 'r' => 4.6, 'f' => 4.8]]),
        $member('u-max', 'Max', ['photo' => $img('av-max.jpg'), 'bio' => 'Swimmer. Will bring snacks.', 'interests' => ['Outdoors'], 'history' => ['n' => 8, 'p' => 4.5, 'r' => 4.8, 'f' => 4.7]]),
        $member('u-lina', 'Lina', ['photo' => $img('av-lina.jpg'), 'bio' => 'Design student who likes picnics and odd games.', 'interests' => ['Weird & Fun', 'Outdoors'], 'history' => ['n' => 4, 'p' => 4.9, 'r' => 4.9, 'f' => 4.4]]),
        $member('u-sam', 'Sam', ['photo' => $img('av-sam.jpg'), 'bio' => 'Board games, coffee and long talks.', 'interests' => ['Local outings'], 'history' => ['n' => 15, 'p' => 4.4, 'r' => 4.8, 'f' => 4.7]]),
    ];
    foreach ($filler as $i => [$id, $name]) {
        $users[] = $member($id, $name, [
            'bio' => $bios[$i % 4], 'interests' => $ints[$i % 4],
            'history' => ['n' => 3 + ($i % 7), 'p' => 4.2 + ($i % 8) / 10, 'r' => 4.4 + ($i % 6) / 10, 'f' => 4.3 + ($i % 7) / 10],
        ]);
    }
    $users[] = [
        'id' => 'u-karl', 'kind' => 'admin', 'role' => 'Moderator', 'name' => 'Karl', 'email' => 'karl@rockon.app',
        'passwordHash' => $hash, 'photo' => $img('av-karl.jpg'), 'joinedAt' => '2026-08-01T10:00:00+00:00', 'suspended' => false,
    ];
    foreach ($users as &$u) {
        if ($u['id'] === 'u-farouk') $u['history'] = ['n' => 20, 'p' => 4.4, 'r' => 4.7, 'f' => 4.8];
        if ($u['id'] === 'u-harish') $u['history'] = ['n' => 3, 'p' => 3.9, 'r' => 3.6, 'f' => 4.1];
    }
    unset($u);

    $A = fn(array $o) => array_merge([
        'joinPolicy' => 'hostApproval', 'status' => 'published', 'minReputation' => null, 'photo' => null,
        'tagline' => '', 'createdAt' => hours_ago(72), 'cancelReason' => '', 'removedByModerator' => false,
    ], $o);

    $activities = [
        $A(['id' => 'a-picnic', 'title' => 'Picnic near the lake!', 'categoryId' => 'outdoors', 'hostId' => 'u-maya',
            'tagline' => 'Let’s enjoy food with a beautiful view :)',
            'description' => 'Bring what you prefer to eat and what’s easier to share. We’ll spread a few blankets by the water and watch the sunset together.',
            'date' => iso_day(5), 'startTime' => '18:00', 'endTime' => '22:00', 'placeId' => 'riverside', 'meetingPoint' => 'East gate',
            'capacity' => 16, 'photo' => $img('picnic.jpg')]),
        $A(['id' => 'a-rooftop', 'title' => 'Rooftop Vibes Party', 'categoryId' => 'party', 'hostId' => 'u-farouk', 'joinPolicy' => 'open',
            'tagline' => 'Music, city lights and new faces',
            'description' => 'A relaxed rooftop evening with a small playlist and a view of the skyline. Soft drinks provided, bring something to share if you like.',
            'date' => iso_day(2), 'startTime' => '21:00', 'endTime' => '23:00', 'placeId' => 'downtown', 'meetingPoint' => 'Lobby of 12 Market St.',
            'capacity' => 25, 'photo' => $img('party-login.jpg')]),
        $A(['id' => 'a-blind', 'title' => 'Blind, Mute and Deaf Game', 'categoryId' => 'weird', 'hostId' => 'u-maya', 'minReputation' => 4.0,
            'tagline' => 'A playful tasting challenge',
            'description' => 'A playful tasting challenge in City Square. Bring a small, labelled snack to share and tell us about any allergies. Participation is always optional.',
            'date' => iso_day(4), 'startTime' => '15:00', 'endTime' => '16:30', 'placeId' => 'citysquare', 'meetingPoint' => 'Fountain entrance',
            'capacity' => 12, 'photo' => $img('blindgame.jpg')]),
        $A(['id' => 'a-sunrise', 'title' => 'Sunrise Park Walk', 'categoryId' => 'outdoors', 'hostId' => 'u-maya',
            'tagline' => 'Catch the first light together',
            'description' => 'An easy 45-minute loop around the park, finishing at the lookout for sunrise. Comfortable shoes recommended; we walk at a chatty pace.',
            'date' => iso_day(3), 'startTime' => '07:00', 'endTime' => '08:00', 'placeId' => 'riverside', 'meetingPoint' => 'Boathouse',
            'capacity' => 16, 'photo' => $img('sunrise.jpg')]),
        $A(['id' => 'a-boardgames', 'title' => 'Board Games & Coffee', 'categoryId' => 'local', 'hostId' => 'u-sam', 'joinPolicy' => 'open',
            'tagline' => 'Learn a new game, meet new people',
            'description' => 'Café table booked for the afternoon. I’ll bring Codenames, Azul and Dixit. Beginners very welcome.',
            'date' => iso_day(1), 'startTime' => '14:00', 'endTime' => '17:00', 'placeId' => 'oldtown', 'meetingPoint' => 'Café Lumen, back room', 'capacity' => 8]),
        $A(['id' => 'a-museum', 'title' => 'Late Night at the Museum', 'categoryId' => 'local', 'hostId' => 'u-clauvie',
            'tagline' => 'Free entry Thursday',
            'description' => 'The city museum is open late with free entry. Let’s explore the modern art floor and grab a hot chocolate after.',
            'date' => iso_day(8), 'startTime' => '19:00', 'endTime' => '21:30', 'placeId' => 'campus', 'meetingPoint' => 'Main steps', 'capacity' => 10]),
        $A(['id' => 'a-kites', 'title' => 'Kite Flying at the Harbour', 'categoryId' => 'weird', 'hostId' => 'u-lina', 'joinPolicy' => 'open',
            'tagline' => 'Homemade kites encouraged',
            'description' => 'Bring a kite, or build one with us from paper and sticks. Prize for the most ridiculous design.',
            'date' => iso_day(12), 'startTime' => '11:00', 'endTime' => '13:00', 'placeId' => 'harbour', 'meetingPoint' => 'Pier 3', 'capacity' => 20]),
        // Completed, inside the 7-day rating window
        $A(['id' => 'a-streetart', 'title' => 'Street Art Tour', 'categoryId' => 'local', 'hostId' => 'u-nour', 'joinPolicy' => 'open',
            'tagline' => 'Murals of the old quarter',
            'description' => 'A two-hour walk through the murals of the old quarter with stories about the artists.',
            'date' => iso_day(-2), 'startTime' => '14:00', 'endTime' => '16:00', 'placeId' => 'oldtown', 'meetingPoint' => 'Clock tower', 'capacity' => 10]),
        // Completed, rating window closed
        $A(['id' => 'a-swim', 'title' => 'Morning Lake Swim', 'categoryId' => 'outdoors', 'hostId' => 'u-max', 'joinPolicy' => 'open',
            'tagline' => 'Cold water, warm company', 'description' => 'A quick dip followed by tea from a flask.',
            'date' => iso_day(-12), 'startTime' => '08:00', 'endTime' => '09:00', 'placeId' => 'riverside', 'meetingPoint' => 'Small beach', 'capacity' => 8]),
    ];

    $pid = 0;
    $P = function (string $m, string $a, string $status = 'accepted', float $h = 48) use (&$pid) {
        return ['id' => 'p-' . (++$pid), 'memberId' => $m, 'activityId' => $a, 'status' => $status, 'joinedAt' => hours_ago($h)];
    };
    $ids = array_column($filler, 0);
    $parts = [];
    foreach (['u-maya', 'u-max', 'u-lina', 'u-sam', 'u-ojaswi'] as $m) $parts[] = $P($m, 'a-picnic');
    foreach (array_slice($ids, 0, 6) as $m) $parts[] = $P($m, 'a-picnic');           // picnic: 11 / 16
    $parts[] = $P('u-farouk', 'a-rooftop');
    $parts[] = $P('u-maya', 'a-rooftop', 'accepted', 30);
    foreach (array_slice($ids, 1, 16) as $m) $parts[] = $P($m, 'a-rooftop');         // rooftop: 18 / 25
    $parts[] = $P('u-maya', 'a-blind');                                              // blind game: host only
    $parts[] = $P('u-maya', 'a-sunrise');
    foreach (array_slice($ids, 5, 10) as $m) $parts[] = $P($m, 'a-sunrise');         // sunrise: 11 / 16
    $parts[] = $P('u-smriti', 'a-sunrise', 'pending', 3);
    $parts[] = $P('u-clauvie', 'a-sunrise', 'pending', 5);
    foreach (['u-sam', 'u-mike', 'u-dara'] as $m) $parts[] = $P($m, 'a-boardgames');
    foreach (['u-clauvie', 'u-nour'] as $m) $parts[] = $P($m, 'a-museum');
    foreach (['u-lina', 'u-max', 'u-yuki'] as $m) $parts[] = $P($m, 'a-kites');
    foreach (['u-nour', 'u-maya', 'u-mike', 'u-ojaswi'] as $m) $parts[] = $P($m, 'a-streetart', 'accepted', 120);
    foreach (['u-max', 'u-maya'] as $m) $parts[] = $P($m, 'a-swim', 'accepted', 400);

    return [
        'version' => 1,
        'users' => $users,
        'categories' => seed_categories(),
        'activities' => $activities,
        'participations' => $parts,
        'ratings' => [
            // Ojaswi already rated Maya → hidden until Maya rates back (NFR-03)
            ['id' => 'r-1', 'raterId' => 'u-ojaswi', 'ratedId' => 'u-maya', 'activityId' => 'a-streetart',
             'punctuality' => 5, 'respect' => 5, 'fun' => 5, 'comment' => 'Lovely company, asked great questions.', 'date' => hours_ago(20), 'isReleased' => false],
        ],
        'messages' => [
            ['id' => 'm-1', 'activityId' => 'a-rooftop', 'memberId' => 'u-farouk', 'content' => 'Welcome everyone! The lift goes straight to the 9th floor.', 'sentAt' => hours_ago(26)],
            ['id' => 'm-2', 'activityId' => 'a-rooftop', 'memberId' => 'u-maya', 'content' => 'Thanks Farouk! Should we bring anything?', 'sentAt' => hours_ago(25)],
            ['id' => 'm-3', 'activityId' => 'a-rooftop', 'memberId' => 'u-farouk', 'content' => 'Just yourselves. Maybe a snack to share 🙂', 'sentAt' => hours_ago(24)],
            ['id' => 'm-4', 'activityId' => 'a-picnic', 'memberId' => 'u-maya', 'content' => 'I’ll bring two big blankets and a speaker. Who has a frisbee?', 'sentAt' => hours_ago(10)],
            ['id' => 'm-5', 'activityId' => 'a-picnic', 'memberId' => 'u-max', 'content' => 'Me! And lemonade.', 'sentAt' => hours_ago(9)],
        ],
        'reports' => [
            ['id' => 'R-026', 'reporterId' => 'u-mooez', 'targetType' => 'activity', 'targetId' => 'a-rooftop', 'reason' => 'Misleading meeting location',
             'summary' => 'The reporter says the meeting address differs from the public listing. The submitted excerpt describes an unannounced location change.',
             'excerpt' => 'The listing said Downtown, but I received a different address without an explanation.', 'date' => hours_ago(30), 'status' => 'inReview', 'resolution' => null],
            ['id' => 'R-027', 'reporterId' => 'u-amara', 'targetType' => 'member', 'targetId' => 'u-harish', 'reason' => 'Unwanted contact',
             'summary' => 'The reporter says this member kept sending private messages after being asked to stop.',
             'excerpt' => 'I said I wasn’t interested and he kept messaging me about meeting alone.', 'date' => hours_ago(28), 'status' => 'open', 'resolution' => null],
            ['id' => 'R-019', 'reporterId' => 'u-leo', 'targetType' => 'activity', 'targetId' => 'a-kites', 'reason' => 'Spam',
             'summary' => 'Reporter thought the listing was an advert. Reviewed: genuine community activity.', 'excerpt' => '',
             'date' => hours_ago(200), 'status' => 'resolved', 'resolution' => 'Closed with no action'],
        ],
        'notifications' => [
            ['id' => 'n-1', 'memberId' => 'u-maya', 'type' => 'request', 'content' => 'Smriti G C requested a place', 'detail' => 'Sunrise Park Walk · Review the request.', 'link' => 'dashboard.php', 'isRead' => false, 'at' => hours_ago(3)],
            ['id' => 'n-2', 'memberId' => 'u-maya', 'type' => 'request', 'content' => 'Clauvie Belange wants to join', 'detail' => 'Sunrise Park Walk · 5 places still open.', 'link' => 'dashboard.php', 'isRead' => false, 'at' => hours_ago(5)],
            ['id' => 'n-3', 'memberId' => 'u-maya', 'type' => 'rating', 'content' => 'How was Street Art Tour?', 'detail' => 'Your feedback window is open for 7 days.', 'link' => 'rate.php?id=a-streetart', 'isRead' => false, 'at' => hours_ago(20)],
            ['id' => 'n-4', 'memberId' => 'u-maya', 'type' => 'accepted', 'content' => 'You’re in: Rooftop Vibes Party', 'detail' => 'The participant chat is now open.', 'link' => 'activity.php?id=a-rooftop', 'isRead' => true, 'at' => hours_ago(30)],
            ['id' => 'n-5', 'memberId' => 'u-ojaswi', 'type' => 'reminder', 'content' => 'Picnic near the lake! is coming up', 'detail' => 'Riverside Park · East gate', 'link' => 'activity.php?id=a-picnic', 'isRead' => false, 'at' => hours_ago(6)],
        ],
    ];
}
