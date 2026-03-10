<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class SecurityMonitoringService
{
    private const ALERT_THRESHOLDS = [
        'failed_logins' => 10,
        'suspicious_requests' => 20,
        'csrf_violations' => 5,
        'file_upload_violations' => 3,
        'rate_limit_violations' => 15,
    ];

    private const ALERT_TIME_WINDOW = 3600; // 1 hour

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuditLogRepository $auditLogRepository,
        private LoggerInterface $logger,
        private RequestStack $requestStack,
        private Security $security,
        private MailerInterface $mailer,
        private string $adminEmail = 'admin@tesda.gov.ph'
    ) {}

    /**
     * Monitor security events and trigger alerts if thresholds are exceeded
     */
    public function monitorSecurityEvents(): void
    {
        $timeWindow = new \DateTime('-' . self::ALERT_TIME_WINDOW . ' seconds');
        
        // Check for various security event patterns
        $this->checkFailedLoginAttempts($timeWindow);
        $this->checkSuspiciousRequests($timeWindow);
        $this->checkCsrfViolations($timeWindow);
        $this->checkFileUploadViolations($timeWindow);
        $this->checkRateLimitViolations($timeWindow);
        $this->checkBruteForcePatterns($timeWindow);
        $this->checkUnusualActivityPatterns($timeWindow);
    }

    /**
     * Get security dashboard data
     */
    public function getSecurityDashboard(): array
    {
        $now = new \DateTime();
        $last24Hours = new \DateTime('-24 hours');
        $lastWeek = new \DateTime('-7 days');

        return [
            'recent_events' => $this->getRecentSecurityEvents(50),
            'event_counts' => $this->getEventCounts($last24Hours),
            'weekly_trends' => $this->getWeeklyTrends($lastWeek),
            'top_threats' => $this->getTopThreats($last24Hours),
            'suspicious_ips' => $this->getSuspiciousIPs($last24Hours),
            'failed_login_attempts' => $this->getFailedLoginAttempts($last24Hours),
            'security_score' => $this->calculateSecurityScore($last24Hours),
            'active_alerts' => $this->getActiveAlerts(),
        ];
    }

    /**
     * Get recent security events
     */
    public function getRecentSecurityEvents(int $limit = 50): array
    {
        return $this->auditLogRepository->findBy(
            ['action' => 'SECURITY_EVENT'],
            ['createdAt' => 'DESC'],
            $limit
        );
    }

    /**
     * Get security event counts for the specified period
     */
    public function getEventCounts(\DateTime $since): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $result = $qb->select('JSON_EXTRACT(al.newValues, \'$.event\') as event_type, COUNT(al.id) as count')
            ->from(AuditLog::class, 'al')
            ->where('al.action = :action')
            ->andWhere('al.createdAt >= :since')
            ->setParameter('action', 'SECURITY_EVENT')
            ->setParameter('since', $since)
            ->groupBy('event_type')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $eventType = trim($row['event_type'], '"');
            $counts[$eventType] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Get weekly security trends
     */
    public function getWeeklyTrends(\DateTime $since): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $result = $qb->select('DATE(al.createdAt) as date, COUNT(al.id) as count')
            ->from(AuditLog::class, 'al')
            ->where('al.action = :action')
            ->andWhere('al.createdAt >= :since')
            ->setParameter('action', 'SECURITY_EVENT')
            ->setParameter('since', $since)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();

        $trends = [];
        foreach ($result as $row) {
            $trends[$row['date']] = (int) $row['count'];
        }

        return $trends;
    }

    /**
     * Get top security threats
     */
    public function getTopThreats(\DateTime $since): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $result = $qb->select('JSON_EXTRACT(al.newValues, \'$.event\') as threat_type, COUNT(al.id) as count, JSON_EXTRACT(al.newValues, \'$.ip\') as ip')
            ->from(AuditLog::class, 'al')
            ->where('al.action = :action')
            ->andWhere('al.createdAt >= :since')
            ->setParameter('action', 'SECURITY_EVENT')
            ->setParameter('since', $since)
            ->groupBy('threat_type', 'ip')
            ->orderBy('count', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $threats = [];
        foreach ($result as $row) {
            $threatType = trim($row['threat_type'], '"');
            $ip = trim($row['ip'], '"');
            $threats[] = [
                'type' => $threatType,
                'count' => (int) $row['count'],
                'ip' => $ip,
                'severity' => $this->calculateThreatSeverity($threatType, (int) $row['count'])
            ];
        }

        return $threats;
    }

    /**
     * Get suspicious IP addresses
     */
    public function getSuspiciousIPs(\DateTime $since): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $result = $qb->select('JSON_EXTRACT(al.newValues, \'$.ip\') as ip, COUNT(al.id) as event_count, JSON_EXTRACT(al.newValues, \'$.event\') as events')
            ->from(AuditLog::class, 'al')
            ->where('al.action = :action')
            ->andWhere('al.createdAt >= :since')
            ->andWhere('JSON_EXTRACT(al.newValues, \'$.ip\') IS NOT NULL')
            ->setParameter('action', 'SECURITY_EVENT')
            ->setParameter('since', $since)
            ->groupBy('ip')
            ->having('event_count >= :threshold')
            ->setParameter('threshold', 5)
            ->orderBy('event_count', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $suspiciousIPs = [];
        foreach ($result as $row) {
            $ip = trim($row['ip'], '"');
            $suspiciousIPs[] = [
                'ip' => $ip,
                'event_count' => (int) $row['event_count'],
                'risk_level' => $this->calculateIPRiskLevel((int) $row['event_count']),
                'location' => $this->getIPLocation($ip),
                'last_activity' => $this->getLastActivityForIP($ip)
            ];
        }

        return $suspiciousIPs;
    }

    /**
     * Get failed login attempts
     */
    public function getFailedLoginAttempts(\DateTime $since): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        return $qb->select('al')
            ->from(AuditLog::class, 'al')
            ->where('al.action = :action')
            ->andWhere('al.createdAt >= :since')
            ->andWhere('JSON_EXTRACT(al.newValues, \'$.event\') = :event')
            ->setParameter('action', 'SECURITY_EVENT')
            ->setParameter('event', 'LOGIN_FAILURE')
            ->setParameter('since', $since)
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate overall security score
     */
    public function calculateSecurityScore(\DateTime $since): array
    {
        $eventCounts = $this->getEventCounts($since);
        $totalEvents = array_sum($eventCounts);
        
        // Base score starts at 100
        $score = 100;
        
        // Deduct points based on security events
        $deductions = [
            'LOGIN_FAILURE' => 1,
            'SUSPICIOUS_INPUT' => 2,
            'CSRF_TOKEN_INVALID' => 3,
            'INVALID_FILE_TYPE' => 2,
            'RATE_LIMIT_EXCEEDED' => 1,
            'BRUTE_FORCE_DETECTED' => 5,
            'SUSPICIOUS_USER_AGENT' => 1,
        ];

        foreach ($eventCounts as $eventType => $count) {
            $deduction = $deductions[$eventType] ?? 1;
            $score -= ($count * $deduction);
        }

        // Ensure score doesn't go below 0
        $score = max(0, $score);

        return [
            'score' => $score,
            'level' => $this->getSecurityLevel($score),
            'total_events' => $totalEvents,
            'recommendations' => $this->getSecurityRecommendations($score, $eventCounts)
        ];
    }

    /**
     * Get active security alerts
     */
    public function getActiveAlerts(): array
    {
        // This would typically be stored in a separate alerts table
        // For now, we'll generate alerts based on recent activity
        $alerts = [];
        $timeWindow = new \DateTime('-1 hour');
        
        $eventCounts = $this->getEventCounts($timeWindow);
        
        foreach (self::ALERT_THRESHOLDS as $eventType => $threshold) {
            $count = $eventCounts[$eventType] ?? 0;
            if ($count >= $threshold) {
                $alerts[] = [
                    'type' => $eventType,
                    'severity' => $this->getAlertSeverity($eventType, $count),
                    'count' => $count,
                    'threshold' => $threshold,
                    'message' => $this->getAlertMessage($eventType, $count),
                    'created_at' => new \DateTime(),
                    'status' => 'active'
                ];
            }
        }

        return $alerts;
    }

    /**
     * Check for failed login attempts and trigger alerts
     */
    private function checkFailedLoginAttempts(\DateTime $since): void
    {
        $failedLogins = $this->getFailedLoginAttempts($since);
        
        if (count($failedLogins) >= self::ALERT_THRESHOLDS['failed_logins']) {
            $this->triggerSecurityAlert('EXCESSIVE_FAILED_LOGINS', [
                'count' => count($failedLogins),
                'threshold' => self::ALERT_THRESHOLDS['failed_logins'],
                'time_window' => self::ALERT_TIME_WINDOW
            ]);
        }
    }

    /**
     * Check for suspicious requests
     */
    private function checkSuspiciousRequests(\DateTime $since): void
    {
        $eventCounts = $this->getEventCounts($since);
        $suspiciousCount = ($eventCounts['SUSPICIOUS_INPUT'] ?? 0) + 
                          ($eventCounts['SUSPICIOUS_USER_AGENT'] ?? 0);
        
        if ($suspiciousCount >= self::ALERT_THRESHOLDS['suspicious_requests']) {
            $this->triggerSecurityAlert('EXCESSIVE_SUSPICIOUS_REQUESTS', [
                'count' => $suspiciousCount,
                'threshold' => self::ALERT_THRESHOLDS['suspicious_requests']
            ]);
        }
    }

    /**
     * Check for CSRF violations
     */
    private function checkCsrfViolations(\DateTime $since): void
    {
        $eventCounts = $this->getEventCounts($since);
        $csrfCount = $eventCounts['CSRF_TOKEN_INVALID'] ?? 0;
        
        if ($csrfCount >= self::ALERT_THRESHOLDS['csrf_violations']) {
            $this->triggerSecurityAlert('EXCESSIVE_CSRF_VIOLATIONS', [
                'count' => $csrfCount,
                'threshold' => self::ALERT_THRESHOLDS['csrf_violations']
            ]);
        }
    }

    /**
     * Check for file upload violations
     */
    private function checkFileUploadViolations(\DateTime $since): void
    {
        $eventCounts = $this->getEventCounts($since);
        $fileCount = ($eventCounts['INVALID_FILE_TYPE'] ?? 0) + 
                    ($eventCounts['EXECUTABLE_FILE_UPLOAD'] ?? 0) +
                    ($eventCounts['SUSPICIOUS_FILE_CONTENT'] ?? 0);
        
        if ($fileCount >= self::ALERT_THRESHOLDS['file_upload_violations']) {
            $this->triggerSecurityAlert('EXCESSIVE_FILE_VIOLATIONS', [
                'count' => $fileCount,
                'threshold' => self::ALERT_THRESHOLDS['file_upload_violations']
            ]);
        }
    }

    /**
     * Check for rate limit violations
     */
    private function checkRateLimitViolations(\DateTime $since): void
    {
        $eventCounts = $this->getEventCounts($since);
        $rateLimitCount = $eventCounts['RATE_LIMIT_EXCEEDED'] ?? 0;
        
        if ($rateLimitCount >= self::ALERT_THRESHOLDS['rate_limit_violations']) {
            $this->triggerSecurityAlert('EXCESSIVE_RATE_LIMIT_VIOLATIONS', [
                'count' => $rateLimitCount,
                'threshold' => self::ALERT_THRESHOLDS['rate_limit_violations']
            ]);
        }
    }

    /**
     * Check for brute force patterns
     */
    private function checkBruteForcePatterns(\DateTime $since): void
    {
        $eventCounts = $this->getEventCounts($since);
        $bruteForceCount = $eventCounts['BRUTE_FORCE_DETECTED'] ?? 0;
        
        if ($bruteForceCount > 0) {
            $this->triggerSecurityAlert('BRUTE_FORCE_ATTACK', [
                'count' => $bruteForceCount,
                'severity' => 'HIGH'
            ]);
        }
    }

    /**
     * Check for unusual activity patterns
     */
    private function checkUnusualActivityPatterns(\DateTime $since): void
    {
        // Check for unusual time-based patterns
        $hourlyActivity = $this->getHourlyActivity($since);
        $avgActivity = array_sum($hourlyActivity) / count($hourlyActivity);
        
        foreach ($hourlyActivity as $hour => $count) {
            if ($count > ($avgActivity * 3)) { // 3x average is unusual
                $this->triggerSecurityAlert('UNUSUAL_ACTIVITY_SPIKE', [
                    'hour' => $hour,
                    'count' => $count,
                    'average' => $avgActivity
                ]);
            }
        }
    }

    /**
     * Trigger a security alert
     */
    private function triggerSecurityAlert(string $alertType, array $data): void
    {
        $this->logger->critical("Security Alert: {$alertType}", $data);
        
        // Send email alert to administrators
        $this->sendSecurityAlert($alertType, $data);
        
        // Log the alert
        $this->logSecurityAlert($alertType, $data);
    }

    /**
     * Send security alert email
     */
    private function sendSecurityAlert(string $alertType, array $data): void
    {
        try {
            $email = (new Email())
                ->from('security@tesda.gov.ph')
                ->to($this->adminEmail)
                ->subject("TESDA Calendar Security Alert: {$alertType}")
                ->html($this->generateAlertEmailContent($alertType, $data));

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send security alert email', [
                'error' => $e->getMessage(),
                'alert_type' => $alertType
            ]);
        }
    }

    /**
     * Log security alert
     */
    private function logSecurityAlert(string $alertType, array $data): void
    {
        $auditLog = new AuditLog();
        $auditLog->setAction('SECURITY_ALERT');
        $auditLog->setEntityType('Security');
        $auditLog->setNewValues([
            'alert_type' => $alertType,
            'data' => $data,
            'timestamp' => new \DateTime()
        ]);
        $auditLog->setDescription("Security alert triggered: {$alertType}");

        $this->entityManager->persist($auditLog);
        $this->entityManager->flush();
    }

    /**
     * Generate alert email content
     */
    private function generateAlertEmailContent(string $alertType, array $data): string
    {
        $content = "<h2>TESDA Calendar Security Alert</h2>";
        $content .= "<p><strong>Alert Type:</strong> {$alertType}</p>";
        $content .= "<p><strong>Time:</strong> " . (new \DateTime())->format('Y-m-d H:i:s') . "</p>";
        $content .= "<h3>Details:</h3><ul>";
        
        foreach ($data as $key => $value) {
            $content .= "<li><strong>{$key}:</strong> " . (is_array($value) ? json_encode($value) : $value) . "</li>";
        }
        
        $content .= "</ul>";
        $content .= "<p>Please review the security dashboard for more details.</p>";
        
        return $content;
    }

    /**
     * Calculate threat severity
     */
    private function calculateThreatSeverity(string $threatType, int $count): string
    {
        $severityMap = [
            'BRUTE_FORCE_DETECTED' => 'HIGH',
            'SUSPICIOUS_INPUT' => 'MEDIUM',
            'CSRF_TOKEN_INVALID' => 'MEDIUM',
            'INVALID_FILE_TYPE' => 'MEDIUM',
            'RATE_LIMIT_EXCEEDED' => 'LOW',
        ];

        $baseSeverity = $severityMap[$threatType] ?? 'LOW';
        
        // Increase severity based on count
        if ($count > 50) return 'CRITICAL';
        if ($count > 20) return 'HIGH';
        if ($count > 10) return 'MEDIUM';
        
        return $baseSeverity;
    }

    /**
     * Calculate IP risk level
     */
    private function calculateIPRiskLevel(int $eventCount): string
    {
        if ($eventCount > 50) return 'CRITICAL';
        if ($eventCount > 20) return 'HIGH';
        if ($eventCount > 10) return 'MEDIUM';
        return 'LOW';
    }

    /**
     * Get IP location (placeholder - would integrate with IP geolocation service)
     */
    private function getIPLocation(string $ip): string
    {
        // This would integrate with a real IP geolocation service
        return 'Unknown';
    }

    /**
     * Get last activity for IP
     */
    private function getLastActivityForIP(string $ip): ?\DateTime
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $result = $qb->select('al.createdAt')
            ->from(AuditLog::class, 'al')
            ->where('JSON_EXTRACT(al.newValues, \'$.ip\') = :ip')
            ->setParameter('ip', $ip)
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ? $result['createdAt'] : null;
    }

    /**
     * Get security level based on score
     */
    private function getSecurityLevel(int $score): string
    {
        if ($score >= 90) return 'EXCELLENT';
        if ($score >= 80) return 'GOOD';
        if ($score >= 70) return 'FAIR';
        if ($score >= 60) return 'POOR';
        return 'CRITICAL';
    }

    /**
     * Get security recommendations
     */
    private function getSecurityRecommendations(int $score, array $eventCounts): array
    {
        $recommendations = [];
        
        if ($score < 70) {
            $recommendations[] = 'Review and strengthen security policies';
        }
        
        if (($eventCounts['LOGIN_FAILURE'] ?? 0) > 20) {
            $recommendations[] = 'Consider implementing additional login security measures';
        }
        
        if (($eventCounts['SUSPICIOUS_INPUT'] ?? 0) > 10) {
            $recommendations[] = 'Review input validation and sanitization';
        }
        
        if (($eventCounts['CSRF_TOKEN_INVALID'] ?? 0) > 5) {
            $recommendations[] = 'Check CSRF token implementation';
        }
        
        return $recommendations;
    }

    /**
     * Get alert severity
     */
    private function getAlertSeverity(string $eventType, int $count): string
    {
        $severityMap = [
            'failed_logins' => 'MEDIUM',
            'suspicious_requests' => 'HIGH',
            'csrf_violations' => 'HIGH',
            'file_upload_violations' => 'MEDIUM',
            'rate_limit_violations' => 'LOW',
        ];

        return $severityMap[$eventType] ?? 'LOW';
    }

    /**
     * Get alert message
     */
    private function getAlertMessage(string $eventType, int $count): string
    {
        $messages = [
            'failed_logins' => "Excessive failed login attempts detected: {$count} attempts in the last hour",
            'suspicious_requests' => "High number of suspicious requests detected: {$count} requests in the last hour",
            'csrf_violations' => "Multiple CSRF token violations detected: {$count} violations in the last hour",
            'file_upload_violations' => "Suspicious file upload attempts detected: {$count} attempts in the last hour",
            'rate_limit_violations' => "Rate limit exceeded multiple times: {$count} violations in the last hour",
        ];

        return $messages[$eventType] ?? "Security threshold exceeded: {$count} events";
    }

    /**
     * Get hourly activity
     */
    private function getHourlyActivity(\DateTime $since): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $result = $qb->select('HOUR(al.createdAt) as hour, COUNT(al.id) as count')
            ->from(AuditLog::class, 'al')
            ->where('al.action = :action')
            ->andWhere('al.createdAt >= :since')
            ->setParameter('action', 'SECURITY_EVENT')
            ->setParameter('since', $since)
            ->groupBy('hour')
            ->getQuery()
            ->getResult();

        $hourlyActivity = array_fill(0, 24, 0);
        foreach ($result as $row) {
            $hourlyActivity[(int) $row['hour']] = (int) $row['count'];
        }

        return $hourlyActivity;
    }
}