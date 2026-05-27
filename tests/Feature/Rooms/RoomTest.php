<?php

use App\Enums\TeamRole;
use App\Models\Message;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use App\Notifications\NewMessage;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->get(route('dashboard', ['current_team' => $team->slug]));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team->slug]));

    $response->assertOk();
    $response->assertSeeLivewire('rooms.index');
});

test('owner can create a room', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Livewire::actingAs($user)
        ->test('rooms.index')
        ->set('newRoomName', 'General')
        ->set('newRoomDescription', 'General discussion')
        ->call('createRoom')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('rooms', [
        'team_id' => $team->id,
        'name' => 'General',
        'description' => 'General discussion',
        'created_by' => $user->id,
    ]);
});

test('admin can create a room', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = $owner->currentTeam;

    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);

    Livewire::actingAs($admin)
        ->test('rooms.index')
        ->set('newRoomName', 'Admin Room')
        ->call('createRoom')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('rooms', [
        'team_id' => $team->id,
        'name' => 'Admin Room',
    ]);
});

test('member cannot create a room', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = $owner->currentTeam;

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    Livewire::actingAs($member)
        ->test('rooms.index')
        ->set('newRoomName', 'Member Room')
        ->call('createRoom')
        ->assertForbidden();

    $this->assertDatabaseMissing('rooms', [
        'team_id' => $team->id,
        'name' => 'Member Room',
    ]);
});

test('room name is required', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('rooms.index')
        ->set('newRoomName', '')
        ->call('createRoom')
        ->assertHasErrors(['newRoomName']);
});

test('room name cannot exceed 255 characters', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('rooms.index')
        ->set('newRoomName', str_repeat('a', 256))
        ->call('createRoom')
        ->assertHasErrors(['newRoomName']);
});

test('creating a room selects it', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('rooms.index')
        ->set('newRoomName', 'General')
        ->call('createRoom')
        ->assertSet('selectedRoomId', function ($value) {
            return $value !== null;
        });
});

test('creating a room clears the create form', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('rooms.index')
        ->set('newRoomName', 'General')
        ->set('showCreateForm', true)
        ->call('createRoom')
        ->assertSet('newRoomName', '')
        ->assertSet('newRoomDescription', null)
        ->assertSet('showCreateForm', false);
});

test('user can select a room', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('rooms.index')
        ->call('selectRoom', $room->id)
        ->assertSet('selectedRoomId', $room->id);
});

test('user cannot select a room from another team', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $room = Room::factory()->create(['team_id' => $otherTeam->id]);

    Livewire::actingAs($user)
        ->test('rooms.index')
        ->call('selectRoom', $room->id)
        ->assertForbidden();
});

test('selected room query param pre-selects a room', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('rooms.index', ['selectedRoomId' => $room->id])
        ->assertSet('selectedRoomId', $room->id);
});

test('selected room from another team is cleared on load', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $room = Room::factory()->create(['team_id' => $otherTeam->id]);

    Livewire::actingAs($user)
        ->test('rooms.index', ['selectedRoomId' => $room->id])
        ->assertSet('selectedRoomId', null);
});

test('user can send a message', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('rooms.index', ['selectedRoomId' => $room->id])
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
        ->test('rooms.index', ['selectedRoomId' => $room->id])
        ->set('body', '')
        ->call('sendMessage')
        ->assertHasErrors(['body']);
});

test('message body cannot exceed 10000 characters', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('rooms.index', ['selectedRoomId' => $room->id])
        ->set('body', str_repeat('a', 10001))
        ->call('sendMessage')
        ->assertHasErrors(['body']);
});

test('sending a message with no room selected does nothing', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('rooms.index')
        ->set('body', 'Hello')
        ->call('sendMessage')
        ->assertHasNoErrors();

    $this->assertDatabaseCount('messages', 0);
});

test('sending a message clears the input', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('rooms.index', ['selectedRoomId' => $room->id])
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
        ->test('rooms.index', ['selectedRoomId' => $room->id])
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
        ->test('rooms.index', ['selectedRoomId' => $room->id])
        ->set('body', 'Hello!')
        ->call('sendMessage')
        ->assertHasNoErrors();

    Notification::assertNotSentTo($sender, NewMessage::class);
});

test('rooms are ordered by name', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $roomB = Room::factory()->create(['team_id' => $team->id, 'name' => 'Beta']);
    $roomA = Room::factory()->create(['team_id' => $team->id, 'name' => 'Alpha']);
    $roomC = Room::factory()->create(['team_id' => $team->id, 'name' => 'Gamma']);

    $names = Livewire::actingAs($user)
        ->test('rooms.index')
        ->get('rooms')
        ->pluck('name')
        ->values()
        ->toArray();

    expect($names)->toBe(['Alpha', 'Beta', 'Gamma']);
});

test('rooms include message count', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $room = Room::factory()->create(['team_id' => $team->id]);
    Message::factory()->count(3)->create(['room_id' => $room->id, 'user_id' => $user->id]);

    $rooms = Livewire::actingAs($user)
        ->test('rooms.index')
        ->get('rooms');

    $found = $rooms->firstWhere('id', $room->id);
    expect($found->messages_count)->toBe(3);
});

test('message sender name is displayed', function () {
    $user = User::factory()->create(['name' => 'Alice']);
    $team = $user->currentTeam;
    $room = Room::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('rooms.index', ['selectedRoomId' => $room->id])
        ->set('body', 'Hello!')
        ->call('sendMessage');

    $message = Message::where('room_id', $room->id)->first();

    Livewire::actingAs($user)
        ->test('rooms.index', ['selectedRoomId' => $room->id])
        ->assertSee('Alice');
});
