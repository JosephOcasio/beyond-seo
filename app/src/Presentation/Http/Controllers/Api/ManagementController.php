<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use App\Infrastructure\Services\AnalyticsService;
use App\Infrastructure\Services\AuditLogService;
use App\Infrastructure\Services\BackupService;
use App\Infrastructure\Services\SchedulerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

/**
 * ManagementController
 *
 * REST API endpoints for advanced plugin management.
 * Includes analytics, audit logs, backups, and scheduling.
 *
 * @Route("/api/management", name="api_management_")
 */
class ManagementController extends AbstractController
{
    /**
     * Get analytics summary
     *
     * @Route("/analytics", name="analytics", methods={"GET"})
     */
    public function analytics(): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            return $this->json([
                'summary' => AnalyticsService::getSummary(),
                'performance' => AnalyticsService::getPerformance(),
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve analytics',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get audit logs
     *
     * @Route("/audit-logs", name="audit_logs", methods={"GET"})
     */
    public function auditLogs(Request $request): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            $filters = [];
            if ($request->query->has('action')) {
                $filters['action'] = $request->query->get('action');
            }
            if ($request->query->has('setting')) {
                $filters['setting'] = $request->query->get('setting');
            }

            $limit = intval($request->query->get('limit', 100));

            $logs = AuditLogService::getLogs($filters, $limit);
            $stats = AuditLogService::getStatistics();

            return $this->json([
                'logs' => $logs,
                'statistics' => $stats,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve audit logs',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Export audit logs as CSV
     *
     * @Route("/audit-logs/export", name="audit_logs_export", methods={"GET"})
     */
    public function exportAuditLogs(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $csv = AuditLogService::exportAsCSV();

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit-logs.csv"',
        ]);
    }

    /**
     * Get backups list
     *
     * @Route("/backups", name="backups", methods={"GET"})
     */
    public function backups(): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            $backups = BackupService::getBackups();

            // Remove settings data from response (too large)
            foreach ($backups as &$backup) {
                unset($backup['settings']);
            }

            return $this->json([
                'backups' => $backups,
                'total' => count($backups),
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve backups',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a backup
     *
     * @Route("/backups", name="backup_create", methods={"POST"})
     */
    public function createBackup(Request $request): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            $data = json_decode($request->getContent(), true);
            $name = $data['name'] ?? null;

            $backup = BackupService::create($name);

            return $this->json([
                'success' => true,
                'backup' => array_diff_key($backup, ['settings' => null]),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to create backup',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restore from backup
     *
     * @Route("/backups/{backup_id}/restore", name="backup_restore", methods={"POST"})
     */
    public function restoreBackup(string $backup_id): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            if (BackupService::restore($backup_id)) {
                return $this->json([
                    'success' => true,
                    'message' => 'Backup restored successfully',
                ], Response::HTTP_OK);
            }

            return $this->json([
                'error' => 'Backup not found',
                'backup_id' => $backup_id,
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to restore backup',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete backup
     *
     * @Route("/backups/{backup_id}", name="backup_delete", methods={"DELETE"})
     */
    public function deleteBackup(string $backup_id): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            if (BackupService::delete($backup_id)) {
                return $this->json([
                    'success' => true,
                    'message' => 'Backup deleted successfully',
                ], Response::HTTP_OK);
            }

            return $this->json([
                'error' => 'Backup not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to delete backup',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get scheduled tasks
     *
     * @Route("/scheduled-tasks", name="scheduled_tasks", methods={"GET"})
     */
    public function scheduledTasks(): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            $tasks = SchedulerService::getTasks();
            $history = SchedulerService::getHistory(limit: 20);

            return $this->json([
                'tasks' => $tasks,
                'recent_executions' => $history,
                'available_types' => SchedulerService::getAvailableTypes(),
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve scheduled tasks',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a scheduled task
     *
     * @Route("/scheduled-tasks", name="task_create", methods={"POST"})
     */
    public function createScheduledTask(Request $request): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            $data = json_decode($request->getContent(), true);

            if (!isset($data['type'], $data['schedule'])) {
                return $this->json([
                    'error' => 'Missing required fields',
                    'required' => ['type', 'schedule'],
                ], Response::HTTP_BAD_REQUEST);
            }

            if (SchedulerService::schedule($data['type'], $data['schedule'])) {
                return $this->json([
                    'success' => true,
                    'message' => 'Task scheduled successfully',
                ], Response::HTTP_CREATED);
            }

            return $this->json([
                'error' => 'Invalid task type',
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to create task',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Disable a scheduled task
     *
     * @Route("/scheduled-tasks/{task_id}/disable", name="task_disable", methods={"POST"})
     */
    public function disableTask(string $task_id): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            if (SchedulerService::disable($task_id)) {
                return $this->json([
                    'success' => true,
                    'message' => 'Task disabled',
                ], Response::HTTP_OK);
            }

            return $this->json([
                'error' => 'Task not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to disable task',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
