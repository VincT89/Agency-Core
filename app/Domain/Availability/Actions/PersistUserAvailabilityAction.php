<?php

namespace App\Domain\Availability\Actions;

use App\Models\User;
use App\Models\UserAvailability;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PersistUserAvailabilityAction
{
    /**
     * @param  array{date: string, starts_at: string, ends_at: string}  $data
     */
    public function execute(
        User $user,
        array $data,
        ?UserAvailability $availability = null
    ): UserAvailability {
        if ($availability && $availability->user_id !== $user->id) {
            throw new AuthorizationException('Non puoi modificare la disponibilità di un altro utente.');
        }

        return DB::transaction(function () use ($user, $data, $availability): UserAvailability {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $startsAt = $this->normalizeTime($data['starts_at']);
            $endsAt = $this->normalizeTime($data['ends_at']);

            $overlapExists = UserAvailability::query()
                ->where('user_id', $user->getKey())
                ->whereDate('date', $data['date'])
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->when(
                    $availability,
                    fn ($query) => $query->whereKeyNot($availability->getKey())
                )
                ->exists();

            if ($overlapExists) {
                throw ValidationException::withMessages([
                    'startsAt' => 'Questa fascia si sovrappone a una disponibilità già inserita.',
                ]);
            }

            $attributes = [
                'date' => $data['date'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ];

            if ($availability) {
                $availability->update($attributes);

                return $availability->refresh();
            }

            return $user->availabilities()->create($attributes);
        });
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
