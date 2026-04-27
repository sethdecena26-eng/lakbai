<?php
// classes/Auth.php

class Auth {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function login(string $email, string $password): bool {
        $stmt = $this->db->prepare(
            'SELECT staff_id, SFN, SLN, email, password, role
             FROM staff WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['staff_id'];
            $_SESSION['SFN']     = $user['SFN'];
            $_SESSION['SLN']     = $user['SLN'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];
            session_regenerate_id(true);
            return true;
        }
        return false;
    }

    public function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
