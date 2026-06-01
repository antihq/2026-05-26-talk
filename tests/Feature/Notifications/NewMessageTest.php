<?php

use App\Models\Message;
use App\Models\Room;
use App\Models\RoomRead;
use App\Models\User;
use App\Notifications\NewMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use NotificationChannels\WebPush\WebPushChannel;

test('notification uses the web push channel', function () {
    $room = Room::factory()->create();
    $sender = User::factory()->create();

    $notification = new NewMessage(
        room: $room,
        sender: $sender,
        body: 'Hello!',
    );

    expect($notification->via($sender))->toBe([WebPushChannel::class]);
});

test('notification is queued', function () {
    $reflection = new ReflectionClass(NewMessage::class);

    expect($reflection->implementsInterface(
        ShouldQueue::class,
    ))->toBeTrue();
});

test('toWebPush returns declarative message with correct structure', function () {
    $sender = User::factory()->create(['name' => 'Alice']);
    $room = Room::factory()->create([
        'name' => 'General',
        'created_by' => $sender->id,
    ]);
    $message = 'Hey everyone!';

    $notification = new NewMessage(
        room: $room,
        sender: $sender,
        body: $message,
    );

    $notifiable = $sender;
    $result = $notification->toWebPush($notifiable, $notification);
    $payload = $result->toArray();

    expect($payload['web_push'])->toBe(8030);
    expect($payload['notification']['title'])->toBe('#General');
    expect($payload['notification']['body'])->toBe('Alice: Hey everyone!');
    expect($payload['notification']['icon'])->toBe('/favicon.ico');
    expect($payload['notification']['navigate'])->toContain('rooms/'.$room->id);
    expect($payload['notification']['actions'][0]['title'])->toBe('Open room');
    expect($payload['notification']['actions'][0]['action'])->toBe('open_room');
    expect($payload['notification']['actions'][0]['navigate'])->toContain('rooms/'.$room->id);
});

test('toWebPush includes unread count in data', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id, 'name' => 'General']);
    Message::factory()->create(['room_id' => $room->id, 'user_id' => $user->id]);

    $notification = new NewMessage(
        room: $room,
        sender: $user,
        body: 'Test',
    );

    $result = $notification->toWebPush($user, $notification);
    $payload = $result->toArray();

    expect($payload['notification']['data'])->toHaveKey('unread_count');
    expect($payload['notification']['data']['unread_count'])->toBe(1);
});

test('toWebPush unread count excludes rooms without messages', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id, 'name' => 'Quiet']);

    $notification = new NewMessage(
        room: $room,
        sender: $user,
        body: 'Test',
    );

    $result = $notification->toWebPush($user, $notification);
    $payload = $result->toArray();

    expect($payload['notification']['data']['unread_count'])->toBe(0);
});

test('toWebPush unread count excludes read rooms', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id, 'name' => 'General']);
    Message::factory()->create(['room_id' => $room->id, 'user_id' => $user->id]);
    RoomRead::create([
        'user_id' => $user->id,
        'room_id' => $room->id,
        'last_read_at' => now(),
    ]);

    $notification = new NewMessage(
        room: $room,
        sender: $user,
        body: 'Test',
    );

    $result = $notification->toWebPush($user, $notification);
    $payload = $result->toArray();

    expect($payload['notification']['data']['unread_count'])->toBe(0);
});

test('toWebPush uses notifiable current team for navigate URL', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $team = $recipient->currentTeam;

    $room = Room::factory()->create([
        'team_id' => $team->id,
        'created_by' => $sender->id,
    ]);

    $notification = new NewMessage(
        room: $room,
        sender: $sender,
        body: 'Hi!',
    );

    $result = $notification->toWebPush($recipient, $notification);
    $payload = $result->toArray();

    $url = $payload['notification']['navigate'];
    expect($url)->toContain($team->slug);
    expect($url)->toContain('rooms/'.$room->id);
});

test('toWebPush unread count sums multiple unread rooms', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $roomA = Room::factory()->create(['team_id' => $team->id]);
    $roomB = Room::factory()->create(['team_id' => $team->id]);
    Message::factory()->create(['room_id' => $roomA->id, 'user_id' => $user->id]);
    Message::factory()->create(['room_id' => $roomB->id, 'user_id' => $user->id]);

    $notification = new NewMessage(
        room: $roomA,
        sender: $user,
        body: 'Test',
    );

    $result = $notification->toWebPush($user, $notification);
    $payload = $result->toArray();

    expect($payload['notification']['data']['unread_count'])->toBe(2);
});
