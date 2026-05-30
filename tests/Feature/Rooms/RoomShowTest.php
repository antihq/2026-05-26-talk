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
