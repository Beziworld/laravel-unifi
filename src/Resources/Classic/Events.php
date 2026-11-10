<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Resources\Classic;

use Beziworld\Unifi\Data\Event;
use Beziworld\Unifi\Support\Values;

/**
 * Events and alarms of the classic API (stat/event, list/alarm, cmd/evtmgr).
 *
 * Availability of stat/event depends on the controller version: newer
 * Network releases move the event log to other endpoints.
 */
final class Events extends ClassicResource
{
    /**
     * Most recent events first.
     *
     * @return list<Event>
     */
    public function recent(int $withinHours = 720, int $start = 0, int $limit = 3000): array
    {
        return array_map(Event::fromArray(...), Values::rows($this->api->request('POST', $this->path('stat/event'), [
            '_sort' => '-time',
            'within' => $withinHours,
            'type' => null,
            '_start' => $start,
            '_limit' => $limit,
        ], idempotent: true)));
    }

    /**
     * @param bool|null $archived false: active alarms only, true: archived only, null: all.
     * @param string|null $key e.g. "EVT_GW_WANTransition".
     * @return list<Event>
     */
    public function alarms(?bool $archived = false, ?string $key = null): array
    {
        $filters = array_filter(['archived' => $archived, 'key' => $key], static fn(bool|string|null $value): bool => $value !== null);

        return array_map(Event::fromArray(...), Values::rows($filters === []
            ? $this->api->request('GET', $this->path('list/alarm'), idempotent: true)
            : $this->api->request('POST', $this->path('list/alarm'), $filters, idempotent: true)));
    }

    /**
     * Number of alarms (active ones only unless $archived is null).
     */
    public function countAlarms(?bool $archived = false): int
    {
        $rows = Values::rows($this->api->request('GET', $this->path('cnt/alarm' . ($archived === false ? '?archived=false' : '')), idempotent: true));

        return Values::int($rows[0] ?? [], 'count');
    }

    /**
     * Archives one alarm, or every alarm when no id is given.
     */
    public function archiveAlarm(?string $alarmId = null): void
    {
        $alarmId === null
            ? $this->command('evtmgr', 'archive-all-alarms')
            : $this->command('evtmgr', 'archive-alarm', ['_id' => $alarmId]);
    }
}
