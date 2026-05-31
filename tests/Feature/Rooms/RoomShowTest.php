<?php

use App\Enums\TeamRole;
use App\Models\Message;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use App\Notifications\NewMessage;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('user can view a room', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('pages::rooms.show', ['room' => $room])
        ->assertOk();
});

test('user cannot view a room from another team', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $room = Room::factory()->create(['team_id' => $otherTeam->id]);

    Livewire::actingAs($user)
        ->test('pages::rooms.show', ['room' => $room])
        ->assertForbidden();
});

test('user can send a message', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('pages::rooms.show', ['room' => $room])
        ->set('body', 'Hello, world!')
        ->call('sendMessage')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('messages', [
        'room_id' => $room->id,
        'user_id' => $user->id,
        'body' => 'Hello, world!',
    ]);
});

test('message body is required', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('pages::rooms.show', ['room' => $room])
        ->set('body', '')
        ->call('sendMessage')
        ->assertHasErrors(['body']);
});

test('message body cannot exceed 10000 characters', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('pages::rooms.show', ['room' => $room])
        ->set('body', str_repeat('a', 10001))
        ->call('sendMessage')
        ->assertHasErrors(['body']);
});

test('sending a message clears the input', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('pages::rooms.show', ['room' => $room])
        ->set('body', 'Hello')
        ->call('sendMessage')
        ->assertSet('body', '')
        ->assertHasNoErrors();
});

test('sending a message notifies other team members', function () {
    $sender = User::factory()->create();
    $memberA = User::factory()->create();
    $memberB = User::factory()->create();
    $team = $sender->currentTeam;

    $team->members()->attach($memberA, ['role' => TeamRole::Member->value]);
    $team->members()->attach($memberB, ['role' => TeamRole::Member->value]);

    $room = Room::factory()->create(['team_id' => $team->id]);

    Notification::fake();

    Livewire::actingAs($sender)
        ->test('pages::rooms.show', ['room' => $room])
        ->set('body', 'Hello everyone!')
        ->call('sendMessage')
        ->assertHasNoErrors();

    Notification::assertSentTo(
        [$memberA, $memberB],
        NewMessage::class,
    );
});

test('sending a message does not notify the sender', function () {
    $sender = User::factory()->create();
    $otherMember = User::factory()->create();
    $team = $sender->currentTeam;

    $team->members()->attach($otherMember, ['role' => TeamRole::Member->value]);

    $room = Room::factory()->create(['team_id' => $team->id]);

    Notification::fake();

    Livewire::actingAs($sender)
        ->test('pages::rooms.show', ['room' => $room])
        ->set('body', 'Hello!')
        ->call('sendMessage')
        ->assertHasNoErrors();

    Notification::assertNotSentTo($sender, NewMessage::class);
});

test('does not notify user currently viewing the room', function () {
    $sender = User::factory()->create();
    $viewer = User::factory()->create();
    $team = $sender->currentTeam;
    $team->members()->attach($viewer, ['role' => TeamRole::Member->value]);
    $room = Room::factory()->create(['team_id' => $team->id]);

    Cache::put("room:{$room->id}:presence:{$viewer->id}", true, 60);

    Notification::fake();

    Livewire::actingAs($sender)
        ->test('pages::rooms.show', ['room' => $room])
        ->set('body', 'Hello!')
        ->call('sendMessage')
        ->assertHasNoErrors();

    Notification::assertNotSentTo($viewer, NewMessage::class);
});

test('message sender name is displayed', function () {
    $user = User::factory()->create(['name' => 'Alice']);
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('pages::rooms.show', ['room' => $room])
        ->set('body', 'Hello!')
        ->call('sendMessage');

    $message = Message::where('room_id', $room->id)->first();

    Livewire::actingAs($user)
        ->test('pages::rooms.show', ['room' => $room])
        ->assertSee('Alice');
});

test('show page displays sent messages', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);
    Message::factory()->create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'body' => 'Hello from the past',
    ]);

    Livewire::actingAs($user)
        ->test('pages::rooms.show', ['room' => $room])
        ->assertSee('Hello from the past');
});

test('consecutive messages from same user within 5 minutes are threaded', function () {
    $user = User::factory()->create(['name' => 'Alice']);
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Message::factory()->create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'body' => 'First',
        'created_at' => now()->subMinutes(4),
    ]);

    Message::factory()->create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'body' => 'Second',
        'created_at' => now()->subMinutes(3),
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::rooms.show', ['room' => $room]);

    $component->assertSee('First')->assertSee('Second');
    expect(substr_count($component->html(), 'Alice'))->toBe(1);
});

test('messages from different users are not threaded', function () {
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);
    $team = $alice->currentTeam;
    $team->members()->attach($bob, ['role' => TeamRole::Member->value]);
    $room = Room::factory()->create(['team_id' => $team->id]);

    Message::factory()->create([
        'room_id' => $room->id,
        'user_id' => $alice->id,
        'body' => 'Hello from A',
        'created_at' => now()->subMinutes(4),
    ]);

    Message::factory()->create([
        'room_id' => $room->id,
        'user_id' => $bob->id,
        'body' => 'Hello from B',
        'created_at' => now()->subMinutes(3),
    ]);

    $component = Livewire::actingAs($alice)
        ->test('pages::rooms.show', ['room' => $room]);

    $component->assertSee('Hello from A')->assertSee('Hello from B');
    expect(substr_count($component->html(), 'Alice'))->toBe(1);
    expect(substr_count($component->html(), 'Bob'))->toBe(1);
});

test('consecutive messages from same user beyond 5 minutes are not threaded', function () {
    $user = User::factory()->create(['name' => 'Alice']);
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Message::factory()->create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'body' => 'First',
        'created_at' => now()->subMinutes(10),
    ]);

    Message::factory()->create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'body' => 'Second',
        'created_at' => now()->subMinutes(3),
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::rooms.show', ['room' => $room]);

    expect(substr_count($component->html(), 'Alice'))->toBe(2);
});

test('first message is never threaded', function () {
    $user = User::factory()->create(['name' => 'Alice']);
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Message::factory()->create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'body' => 'Lonely message',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::rooms.show', ['room' => $room]);

    $component->assertSee('Lonely message');
    expect(substr_count($component->html(), 'Alice'))->toBe(1);
});
