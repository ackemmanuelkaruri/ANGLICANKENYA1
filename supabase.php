<?php
/**
 * Supabase PHP Connection Class
 * For Church Management System
 */

class SupabaseDB {
    private $supabaseUrl;
    private $supabaseKey;
    private $headers;

    public function __construct() {
        // Replace these with your actual Supabase credentials
        $this->supabaseUrl = 'https://iyztzrvjcdqotcqqekkw.supabase.co';
        $this->supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Iml5enR6cnZqY2Rxb3RjcXFla2t3Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjEyNTU4NTYsImV4cCI6MjA3NjgzMTg1Nn0.BjY-M47P7cVeyDqZFS7sXwc5yz2HxGkvzbMdy5rIwd8'; // Replace with your actual key
        
        $this->headers = [
            'apikey: ' . $this->supabaseKey,
            'Authorization: Bearer ' . $this->supabaseKey,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];
    }

    /**
     * SELECT - Fetch data from table
     * @param string $table - Table name
     * @param array $conditions - WHERE conditions ['column' => 'value']
     * @param string $select - Columns to select (default: *)
     * @return array|false
     */
    public function select($table, $conditions = [], $select = '*') {
        $url = $this->supabaseUrl . '/rest/v1/' . $table . '?select=' . $select;
        
        // Add conditions
        foreach ($conditions as $column => $value) {
            $url .= '&' . $column . '=eq.' . urlencode($value);
        }
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * INSERT - Add new record
     * @param string $table - Table name
     * @param array $data - Data to insert
     * @return array|false
     */
    public function insert($table, $data) {
        $url = $this->supabaseUrl . '/rest/v1/' . $table;
        return $this->makeRequest($url, 'POST', $data);
    }

    /**
     * UPDATE - Update existing record
     * @param string $table - Table name
     * @param array $data - Data to update
     * @param array $conditions - WHERE conditions
     * @return array|false
     */
    public function update($table, $data, $conditions) {
        $url = $this->supabaseUrl . '/rest/v1/' . $table . '?';
        
        foreach ($conditions as $column => $value) {
            $url .= $column . '=eq.' . urlencode($value) . '&';
        }
        $url = rtrim($url, '&');
        
        return $this->makeRequest($url, 'PATCH', $data);
    }

    /**
     * DELETE - Remove record
     * @param string $table - Table name
     * @param array $conditions - WHERE conditions
     * @return array|false
     */
    public function delete($table, $conditions) {
        $url = $this->supabaseUrl . '/rest/v1/' . $table . '?';
        
        foreach ($conditions as $column => $value) {
            $url .= $column . '=eq.' . urlencode($value) . '&';
        }
        $url = rtrim($url, '&');
        
        return $this->makeRequest($url, 'DELETE');
    }

    /**
     * LOGIN - Authenticate user
     * @param string $email
     * @param string $password
     * @return array|false
     */
    public function login($email, $password) {
        // First, get user by email
        $user = $this->select('users', ['email' => $email]);
        
        if ($user && count($user) > 0) {
            // Verify password (assuming you're using password_hash)
            if (password_verify($password, $user[0]['password'])) {
                return $user[0];
            }
        }
        return false;
    }

    /**
     * REGISTER - Create new user
     * @param array $userData
     * @return array|false
     */
    public function register($userData) {
        // Hash password before storing
        if (isset($userData['password'])) {
            $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
        }
        
        return $this->insert('users', $userData);
    }

    /**
     * Custom Query with Filters
     * @param string $table
     * @param array $filters - ['column' => ['operator' => 'eq', 'value' => 'something']]
     * @return array|false
     */
    public function query($table, $filters = []) {
        $url = $this->supabaseUrl . '/rest/v1/' . $table . '?';
        
        foreach ($filters as $column => $filter) {
            $operator = $filter['operator'] ?? 'eq';
            $value = $filter['value'];
            $url .= $column . '=' . $operator . '.' . urlencode($value) . '&';
        }
        $url = rtrim($url, '&');
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Make HTTP Request
     * @param string $url
     * @param string $method
     * @param array $data
     * @return array|false
     */
    private function makeRequest($url, $method, $data = null) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }
        
        return false;
    }
}

// ============================================
// USAGE EXAMPLES
// ============================================

// Initialize connection
$db = new SupabaseDB();

// Example 1: User Registration
/*
$newUser = [
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john@example.com',
    'password' => 'secure123',
    'phone' => '+254712345678',
    'created_at' => date('Y-m-d H:i:s')
];
$result = $db->register($newUser);
if ($result) {
    echo "User registered successfully!";
}
*/

// Example 2: User Login
/*
$email = 'john@example.com';
$password = 'secure123';
$user = $db->login($email, $password);
if ($user) {
    $_SESSION['user_id'] = $user['id'];
    echo "Login successful!";
} else {
    echo "Invalid credentials!";
}
*/

// Example 3: Fetch all members from a parish
/*
$members = $db->select('users', ['parish_id' => 1]);
foreach ($members as $member) {
    echo $member['first_name'] . ' ' . $member['last_name'] . '<br>';
}
*/

// Example 4: Record giving/tithe
/*
$giving = [
    'user_id' => 1,
    'amount' => 5000,
    'method' => 'Mpesa',
    'transaction_code' => 'QGH123456',
    'giving_date' => date('Y-m-d'),
    'created_at' => date('Y-m-d H:i:s')
];
$result = $db->insert('givings', $giving);
*/

// Example 5: Update member details
/*
$updateData = [
    'phone' => '+254700000000',
    'address' => 'Nairobi, Kenya'
];
$result = $db->update('users', $updateData, ['id' => 1]);
*/

// Example 6: Get all givings for a specific user
/*
$userGivings = $db->select('givings', ['user_id' => 1]);
$total = array_sum(array_column($userGivings, 'amount'));
echo "Total givings: KES " . number_format($total);
*/

// Example 7: Fetch events
/*
$events = $db->select('events', [], 'id,title,event_date,location');
*/

// Example 8: Record attendance
/*
$attendance = [
    'user_id' => 1,
    'event_id' => 5,
    'attendance_date' => date('Y-m-d'),
    'status' => 'present'
];
$db->insert('attendance_records', $attendance);
*/

// Example 9: Search users by name
/*
$searchResults = $db->query('users', [
    'first_name' => ['operator' => 'ilike', 'value' => '%john%']
]);
*/

// Example 10: Delete a record (use carefully!)
/*
$db->delete('login_attempts', ['id' => 123]);
*/

?>