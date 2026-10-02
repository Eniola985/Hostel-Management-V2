<?php
session_start();

require_once '../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized.'
    ]);
    exit;
}

$student_id = (int)$_SESSION['student_id'];
$hostel_id = (int)($_GET['hostel_id'] ?? 0);

if ($hostel_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid hostel.'
    ]);
    exit;
}

/*
 * Get the logged-in student's gender.
 * Students must only be able to view rooms in hostels
 * assigned to their gender.
 */
$studentStmt = $pdo->prepare(
    "SELECT gender
     FROM students
     WHERE student_id=?
     LIMIT 1"
);
$studentStmt->execute([$student_id]);
$student = $studentStmt->fetch();

if (!$student) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Student account not found.'
    ]);
    exit;
}

/*
 * Get the selected hostel and verify that it belongs
 * to the student's gender.
 */
$hostelStmt = $pdo->prepare(
    "SELECT hostel_id, hostel_name, hostel_type
     FROM hostels
     WHERE hostel_id=?
       AND hostel_type=?
     LIMIT 1"
);
$hostelStmt->execute([
    $hostel_id,
    $student['gender']
]);

$hostel = $hostelStmt->fetch();

if (!$hostel) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'You cannot view rooms in this hostel.'
    ]);
    exit;
}

/*
 * Get rooms that still have space.
 *
 * We calculate availability from capacity and occupied
 * rather than trusting the status field alone.
 */
$roomStmt = $pdo->prepare(
    "SELECT
        room_id,
        room_number,
        capacity,
        occupied,
        GREATEST(capacity - occupied, 0) AS available_spaces
     FROM rooms
     WHERE hostel_id=?
       AND status='Available'
       AND occupied < capacity
     ORDER BY room_number"
);

$roomStmt->execute([$hostel_id]);

$rooms = $roomStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'hostel' => [
        'hostel_id' => (int)$hostel['hostel_id'],
        'hostel_name' => $hostel['hostel_name'],
        'hostel_type' => $hostel['hostel_type']
    ],
    'rooms' => array_map(function ($room) {
        return [
            'room_id' => (int)$room['room_id'],
            'room_number' => $room['room_number'],
            'capacity' => (int)$room['capacity'],
            'occupied' => (int)$room['occupied'],
            'available_spaces' => (int)$room['available_spaces']
        ];
    }, $rooms)
]);