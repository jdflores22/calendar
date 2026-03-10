<?php

namespace App\Command;

use App\Service\SecurityMonitoringService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:security:monitor',
    description: 'Run security monitoring and alert checks'
)]
class SecurityMonitorCommand extends Command
{
    public function __construct(
        private SecurityMonitoringService $securityMonitoring
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('TESDA Calendar Security Monitor');

        try {
            $io->section('Running security monitoring...');
            $this->securityMonitoring->monitorSecurityEvents();
            
            $io->section('Getting security dashboard data...');
            $dashboardData = $this->securityMonitoring->getSecurityDashboard();
            
            // Display security score
            $securityScore = $dashboardData['security_score'];
            $io->info(sprintf(
                'Security Score: %d/100 (%s)',
                $securityScore['score'],
                $securityScore['level']
            ));
            
            // Display active alerts
            $activeAlerts = $dashboardData['active_alerts'];
            if (!empty($activeAlerts)) {
                $io->warning(sprintf('Found %d active security alerts:', count($activeAlerts)));
                
                foreach ($activeAlerts as $alert) {
                    $io->writeln(sprintf(
                        '  - %s: %s [%s]',
                        $alert['type'],
                        $alert['message'],
                        $alert['severity']
                    ));
                }
            } else {
                $io->success('No active security alerts');
            }
            
            // Display event counts
            $eventCounts = $dashboardData['event_counts'];
            if (!empty($eventCounts)) {
                $io->section('Security Events (Last 24 Hours):');
                
                foreach ($eventCounts as $eventType => $count) {
                    $io->writeln(sprintf('  %s: %d', $eventType, $count));
                }
            }
            
            // Display top threats
            $topThreats = $dashboardData['top_threats'];
            if (!empty($topThreats)) {
                $io->section('Top Security Threats:');
                
                foreach (array_slice($topThreats, 0, 5) as $threat) {
                    $io->writeln(sprintf(
                        '  %s from %s: %d events [%s]',
                        $threat['type'],
                        $threat['ip'],
                        $threat['count'],
                        $threat['severity']
                    ));
                }
            }
            
            // Display suspicious IPs
            $suspiciousIPs = $dashboardData['suspicious_ips'];
            if (!empty($suspiciousIPs)) {
                $io->section('Suspicious IP Addresses:');
                
                foreach (array_slice($suspiciousIPs, 0, 5) as $ipData) {
                    $io->writeln(sprintf(
                        '  %s: %d events [%s]',
                        $ipData['ip'],
                        $ipData['event_count'],
                        $ipData['risk_level']
                    ));
                }
            }
            
            $io->success('Security monitoring completed successfully');
            
        } catch (\Exception $e) {
            $io->error('Security monitoring failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}