<?php

use App\Models\Room;
use App\Models\User;
use App\Services\RoomPresence;
use Pusher\Pusher;

beforeEach(function () {
    $this->pusher = Mockery::mock(Pusher::class);
});

function presenceService(mixed $pusher): RoomPresence
{
    $service = Mockery::mock(RoomPresence::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('pusher')->andReturn($pusher);

    return $service;
}

test('returns user ids from pusher presence response', function () {
    $room = Room::factory()->create(['team_id' => User::factory()->create()->currentTeam->id]);

    $this->pusher->shouldReceive('getPresenceUsers')
        ->with("presence-room.{$room->id}")
        ->andReturn((object) ['users' => [
            (object) ['id' => '1'],
            (object) ['id' => '2'],
        ]]);

    $ids = presenceService($this->pusher)->subscribedUserIds($room);

    expect($ids)->toBe([1, 2]);
});

test('casts string user ids to integers', function () {
    $room = Room::factory()->create(['team_id' => User::factory()->create()->currentTeam->id]);

    $this->pusher->shouldReceive('getPresenceUsers')
        ->andReturn((object) ['users' => [
            (object) ['id' => '42'],
        ]]);

    $ids = presenceService($this->pusher)->subscribedUserIds($room);

    expect($ids[0])->toBeInt()->toBe(42);
});

test('returns empty array when no users are present', function () {
    $room = Room::factory()->create(['team_id' => User::factory()->create()->currentTeam->id]);

    $this->pusher->shouldReceive('getPresenceUsers')
        ->andReturn((object) ['users' => []]);

    $ids = presenceService($this->pusher)->subscribedUserIds($room);

    expect($ids)->toBe([]);
});

test('returns empty array when pusher throws an exception', function () {
    $room = Room::factory()->create(['team_id' => User::factory()->create()->currentTeam->id]);

    $this->pusher->shouldReceive('getPresenceUsers')
        ->andThrow(new \Exception('API unreachable'));

    $ids = presenceService($this->pusher)->subscribedUserIds($room);

    expect($ids)->toBe([]);
});
