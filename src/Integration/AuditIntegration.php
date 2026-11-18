<?php

namespace HuseyinFiliz\Rewind\Integration;

use Flarum\Audit\AuditLogger;
use HuseyinFiliz\Rewind\Event\SnapshotGenerated;
use HuseyinFiliz\Rewind\Event\SnapshotsDeleted;
use HuseyinFiliz\Rewind\Event\TemplateChanged;
use HuseyinFiliz\Rewind\Model\RewindSnapshot;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;

class AuditIntegration
{
    /** @var string[] */
    public static array $actions = [
        'rewind.personal.created',
        'rewind.personal.regenerated',
        'rewind.personal.visibility_changed',
        'rewind.personal.deleted',
        'rewind.personal.batch_deleted',
        'rewind.community.created',
        'rewind.community.regenerated',
        'rewind.template.created',
        'rewind.template.updated',
        'rewind.template.deleted',
    ];

    public function __invoke(Container $container): void
    {
        $events = $container->make(Dispatcher::class);

        $events->listen(SnapshotGenerated::class, function (SnapshotGenerated $event): void {
            $snapshot = $event->snapshot;
            $personal = $snapshot instanceof RewindSnapshot;
            $payload = ['snapshot_id' => (int) $snapshot->id, 'year' => (int) $snapshot->year];
            if ($personal) {
                $payload['user_id'] = (int) $snapshot->user_id;
            }

            AuditLogger::log('rewind.'.($personal ? 'personal' : 'community').'.'.($event->regenerated ? 'regenerated' : 'created'), $payload);
        });

        RewindSnapshot::updated(function (RewindSnapshot $snapshot): void {
            if (! $snapshot->wasChanged('is_public')) {
                return;
            }

            AuditLogger::log('rewind.personal.visibility_changed', [
                'snapshot_id' => (int) $snapshot->id,
                'user_id' => (int) $snapshot->user_id,
                'year' => (int) $snapshot->year,
                'old_visibility' => $snapshot->getRawOriginal('is_public') ? 'public' : 'private',
                'visibility' => $snapshot->is_public ? 'public' : 'private',
            ]);
        });

        RewindSnapshot::deleted(function (RewindSnapshot $snapshot): void {
            AuditLogger::log('rewind.personal.deleted', [
                'snapshot_id' => (int) $snapshot->id,
                'user_id' => (int) $snapshot->user_id,
                'year' => (int) $snapshot->year,
            ]);
        });

        $events->listen(SnapshotsDeleted::class, function (SnapshotsDeleted $event): void {
            if ($event->count > 0) {
                AuditLogger::log('rewind.personal.batch_deleted', [
                    'year' => $event->year,
                    'group_id' => $event->groupId,
                    'deleted_count' => $event->count,
                ]);
            }
        });

        $events->listen(TemplateChanged::class, function (TemplateChanged $event): void {
            if (in_array($event->operation, ['created', 'updated', 'deleted'], true)) {
                AuditLogger::log('rewind.template.'.$event->operation, ['template_id' => $event->id]);
            }
        });
    }
}
