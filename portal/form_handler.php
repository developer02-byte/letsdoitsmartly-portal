<?php
define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

requireLogin();

// Verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        if (isset($_POST['action']) && $_POST['action'] === 'save_draft') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid security token']);
            exit;
        }
        header('Location: form.php?error=' . urlencode('Invalid security token'));
        exit;
    }
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'save_draft':
        saveDraft();
        break;
    case 'submit':
        submitQuestionnaire();
        break;
    case 'delete':
        deleteSubmission();
        break;
    default:
        header('Location: form.php');
        exit;
}

function saveDraft() {
    global $conn;

    $submission_id = intval($_POST['submission_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    // Get primary contact person data (first contact)
    $contact_persons = $_POST['contact_persons'] ?? [];
    $primary_contact = $contact_persons[0] ?? [];

    // Sanitize and validate basic fields
    $client_email = filter_var($primary_contact['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $client_name = sanitize($primary_contact['name'] ?? '');
    $client_phone = sanitize($primary_contact['phone'] ?? '');

    // Encode all form data as JSON
    $form_data = json_encode($_POST, JSON_UNESCAPED_UNICODE);

    if ($submission_id > 0) {
        // Update existing draft
        $stmt = $conn->prepare("UPDATE questionnaire_submissions
                               SET form_data = ?, client_email = ?, client_name = ?, client_phone = ?,
                                   updated_at = NOW()
                               WHERE id = ? AND user_id = ? AND submission_status = 'draft'");
        $stmt->bind_param('ssssii', $form_data, $client_email, $client_name, $client_phone,
                         $submission_id, $user_id);

        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'submission_id' => $submission_id]);
            exit;
        }
    } else {
        // Create new draft
        $stmt = $conn->prepare("INSERT INTO questionnaire_submissions
                               (user_id, client_email, client_name, client_phone, form_data, submission_status)
                               VALUES (?, ?, ?, ?, ?, 'draft')");
        $stmt->bind_param('issss', $user_id, $client_email, $client_name, $client_phone, $form_data);

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;

            // Log activity
            logActivity($conn, "Created questionnaire draft", null, null, [
                'category' => 'questionnaire',
                'resource_type' => 'questionnaire_submission',
                'resource_id' => $new_id,
                'client_name' => $client_name
            ]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'submission_id' => $new_id]);
            exit;
        }
    }

    // If we get here, something went wrong
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to save draft']);
    exit;
}

function submitQuestionnaire() {
    global $conn;

    $submission_id = intval($_POST['submission_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    // Validate contact persons array
    $contact_persons = $_POST['contact_persons'] ?? [];
    if (empty($contact_persons) || empty($contact_persons[0]['name']) || empty($contact_persons[0]['email'])) {
        header('Location: form.php?error=' . urlencode('At least one contact person with name and email is required'));
        exit;
    }

    // Validate required fields
    $required_fields = ['business_name', 'success_definition', 'primary_cta'];
    $missing_fields = [];

    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }

    if (!empty($missing_fields)) {
        header('Location: form.php?error=' . urlencode('Please fill in all required fields: ' . implode(', ', $missing_fields)));
        exit;
    }

    // Get primary contact person data (first contact)
    $primary_contact = $contact_persons[0];

    // Sanitize and validate
    $client_email = filter_var($primary_contact['email'], FILTER_VALIDATE_EMAIL);
    if (!$client_email) {
        header('Location: form.php?error=' . urlencode('Invalid email address for primary contact person'));
        exit;
    }

    $client_name = sanitize($primary_contact['name']);
    $client_phone = sanitize($primary_contact['phone'] ?? '');

    // Encode all form data as JSON
    $form_data = json_encode($_POST, JSON_UNESCAPED_UNICODE);

    if ($submission_id > 0) {
        // Update existing submission
        $stmt = $conn->prepare("UPDATE questionnaire_submissions
                               SET form_data = ?, client_email = ?, client_name = ?, client_phone = ?,
                                   submission_status = 'submitted', submitted_at = NOW(), updated_at = NOW()
                               WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ssssii', $form_data, $client_email, $client_name, $client_phone,
                         $submission_id, $user_id);
    } else {
        // Create new submission
        $stmt = $conn->prepare("INSERT INTO questionnaire_submissions
                               (user_id, client_email, client_name, client_phone, form_data, submission_status, submitted_at)
                               VALUES (?, ?, ?, ?, ?, 'submitted', NOW())");
        $stmt->bind_param('issss', $user_id, $client_email, $client_name, $client_phone, $form_data);
    }

    if ($stmt->execute()) {
        $final_id = $submission_id > 0 ? $submission_id : $conn->insert_id;

        // Log activity
        logActivity($conn, "Submitted client questionnaire", null, null, [
            'category' => 'questionnaire',
            'resource_type' => 'questionnaire_submission',
            'resource_id' => $final_id,
            'client_name' => $client_name,
            'client_email' => $client_email
        ]);

        header('Location: form.php?success=submitted');
        exit;
    } else {
        header('Location: form.php?error=' . urlencode('Failed to submit questionnaire. Please try again.'));
        exit;
    }
}

function deleteSubmission() {
    global $conn;

    $submission_id = intval($_POST['submission_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    if ($submission_id <= 0) {
        header('Location: form.php?error=' . urlencode('Invalid submission ID'));
        exit;
    }

    // Only allow users to delete their own submissions
    $stmt = $conn->prepare("DELETE FROM questionnaire_submissions WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $submission_id, $user_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        // Log activity
        logActivity($conn, "Deleted questionnaire submission", null, null, [
            'category' => 'questionnaire',
            'resource_type' => 'questionnaire_submission',
            'resource_id' => $submission_id
        ]);

        header('Location: form.php?success=deleted');
        exit;
    } else {
        header('Location: form.php?error=' . urlencode('Failed to delete submission'));
        exit;
    }
}
