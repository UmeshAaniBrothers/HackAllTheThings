<?php

/**
 * UserManager - Multi-User System with Roles & API Keys
 *
 * Manages user accounts, authentication, role-based access,
 * API key generation, and usage tracking.
 */
class UserManager
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    // =========================================================
    // User CRUD
    // =========================================================

    public function createUser(string $email, string $password, string $name = '', string $role = 'analyst'): string
    {
        $apiKey = $this->generateApiKey();

        return $this->db->insert('users', [
            'email'         => strtolower(trim($email)),
            'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'name'          => $name,
            'role'          => $role,
            'api_key'       => $apiKey,
        ]);
    }

    public function updateUser(int $userId, array $data): int
    {
        $allowed = ['name', 'role', 'is_active', 'api_rate_limit'];
        $filtered = array_intersect_key($data, array_flip($allowed));

        if (empty($filtered)) return 0;

        return $this->db->update('users', $filtered, 'id = ?', [$userId]);
    }

    public function deleteUser(int $userId): int
    {
        return $this->db->query("DELETE FROM users WHERE id = ?", [$userId])->rowCount();
    }

    public function getUser(int $userId): ?array
    {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        if ($user) unset($user['password_hash']);
        return $user;
    }

    public function getAllUsers(): array
    {
        $users = $this->db->fetchAll(
            "SELECT id, email, name, role, api_key, api_rate_limit, last_login_at, is_active, created_at
             FROM users ORDER BY created_at DESC"
        );
        return $users;
    }

    // =========================================================
    // Authentication
    // =========================================================

    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE email = ? AND is_active = 1",
            [strtolower(trim($email))]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        $this->db->update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);

        unset($user['password_hash']);
        return $user;
    }

    public function changePassword(int $userId, string $newPassword): bool
    {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        return $this->db->update('users', ['password_hash' => $hash], 'id = ?', [$userId]) > 0;
    }

    // =========================================================
    // API Key Management
    // =========================================================

    public function authenticateApiKey(string $apiKey): ?array
    {
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE api_key = ? AND is_active = 1",
            [$apiKey]
        );

        if (!$user) return null;
        unset($user['password_hash']);
        return $user;
    }

    public function regenerateApiKey(int $userId): ?string
    {
        $newKey = $this->generateApiKey();
        $updated = $this->db->update('users', ['api_key' => $newKey], 'id = ?', [$userId]);
        return $updated > 0 ? $newKey : null;
    }

    /**
     * Check API rate limit for a user.
     */
    public function checkRateLimit(string $apiKey): bool
    {
        $user = $this->db->fetchOne("SELECT id, api_rate_limit FROM users WHERE api_key = ?", [$apiKey]);
        if (!$user) return false;

        $recentCalls = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM api_usage_log WHERE api_key = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$apiKey]
        );

        return $recentCalls < $user['api_rate_limit'];
    }

    /**
     * Log an API usage event.
     */
    public function logApiUsage(string $apiKey, string $endpoint, string $method, int $responseCode): void
    {
        $this->db->insert('api_usage_log', [
            'api_key'       => $apiKey,
            'endpoint'      => $endpoint,
            'method'        => $method,
            'response_code' => $responseCode,
            'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    /**
     * Get API usage stats for a user.
     */
    public function getApiUsageStats(string $apiKey): array
    {
        return [
            'last_hour' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM api_usage_log WHERE api_key = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                [$apiKey]
            ),
            'last_24h' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM api_usage_log WHERE api_key = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                [$apiKey]
            ),
            'top_endpoints' => $this->db->fetchAll(
                "SELECT endpoint, COUNT(*) as calls FROM api_usage_log WHERE api_key = ? GROUP BY endpoint ORDER BY calls DESC LIMIT 10",
                [$apiKey]
            ),
        ];
    }

    // =========================================================
    // Saved Dashboards
    // =========================================================

    public function saveDashboard(int $userId, string $name, array $config): string
    {
        return $this->db->insert('user_saved_dashboards', [
            'user_id' => $userId,
            'name'    => $name,
            'config'  => json_encode($config),
        ]);
    }

    public function getUserDashboards(int $userId): array
    {
        $dashboards = $this->db->fetchAll(
            "SELECT * FROM user_saved_dashboards WHERE user_id = ? ORDER BY created_at DESC",
            [$userId]
        );

        foreach ($dashboards as &$d) {
            $d['config'] = json_decode($d['config'], true);
        }

        return $dashboards;
    }

    public function deleteDashboard(int $dashboardId, int $userId): int
    {
        return $this->db->query(
            "DELETE FROM user_saved_dashboards WHERE id = ? AND user_id = ?",
            [$dashboardId, $userId]
        )->rowCount();
    }

    // =========================================================
    // Authorization Helpers
    // =========================================================

    public function hasRole(array $user, string $requiredRole): bool
    {
        $hierarchy = ['viewer' => 1, 'analyst' => 2, 'admin' => 3];
        $userLevel = $hierarchy[$user['role']] ?? 0;
        $requiredLevel = $hierarchy[$requiredRole] ?? 0;
        return $userLevel >= $requiredLevel;
    }

    private function generateApiKey(): string
    {
        return bin2hex(random_bytes(32));
    }
}
