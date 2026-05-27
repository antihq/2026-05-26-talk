<?php

use App\Models\Room;
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
    expect($payload['notification']['navigate'])->toContain('?room='.$room->id);
    expect($payload['notification']['actions'][0]['title'])->toBe('Open room');
    expect($payload['notification']['actions'][0]['action'])->toBe('open_room');
    expect($payload['notification']['actions'][0]['navigate'])->toContain('?room='.$room->id);
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
    expect($url)->toContain('?room='.$room->id);
});
