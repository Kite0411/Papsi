<?php
include '../includes/config.php';
session_start();
$conn = getDBConnection();

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

$message = '';
$messageType = 'info';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_knowledge':
                $trigger = trim($_POST['trigger'] ?? '');
                $response = trim($_POST['response'] ?? '');
                
                if ($trigger && $response) {
                    $stmt = $conn->prepare("INSERT INTO chat_knowledge (trigger1, response) VALUES (?, ?)");
                    $stmt->bind_param("ss", $trigger, $response);
                    if ($stmt->execute()) {
                        $message = "Knowledge added successfully!";
                        $messageType = 'success';
                    } else {
                        $message = "Error adding knowledge.";
                        $messageType = 'danger';
                    }
                    $stmt->close();
                } else {
                    $message = "Both trigger and response are required.";
                    $messageType = 'warning';
                }
                break;
                
            case 'delete_knowledge':
                $id = (int)$_POST['knowledge_id'];
                $stmt = $conn->prepare("DELETE FROM chat_knowledge WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $message = "Knowledge deleted successfully!";
                    $messageType = 'success';
                } else {
                    $message = "Error deleting knowledge.";
                    $messageType = 'danger';
                }
                $stmt->close();
                break;
                
            case 'clear_history':
                $stmt = $conn->prepare("DELETE FROM chat_history");
                if ($stmt->execute()) {
                    $message = "Chat history cleared successfully!";
                    $messageType = 'success';
                } else {
                    $message = "Error clearing chat history.";
                    $messageType = 'danger';
                }
                $stmt->close();
                break;
        }
    }
}

// Get statistics
$stats = [];
$result = $conn->query("SELECT COUNT(*) as total FROM chat_knowledge");
$stats['knowledge'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM chat_history");
$stats['conversations'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM chat_history WHERE DATE(created_at) = CURDATE()");
$stats['today'] = $result->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Chatbot - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #0066cc, #004d99);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .knowledge-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #0066cc;
        }
        .chat-history-item {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }
        .user-message {
            background: #e3f2fd;
            border-left: 4px solid #0066cc;
        }
        .bot-message {
            background: #f5f5f5;
            border-left: 4px solid #4CAF50;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">AutoFix Admin</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Dashboard</a>
                <a class="nav-link active" href="manage_chatbot.php">Chatbot</a>
                <a class="nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="fas fa-robot"></i> Chatbot Management</h2>
                
                <?php if($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card text-center">
                    <i class="fas fa-brain fa-2x mb-2"></i>
                    <h4><?php echo $stats['knowledge']; ?></h4>
                    <p>Knowledge Entries</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card text-center">
                    <i class="fas fa-comments fa-2x mb-2"></i>
                    <h4><?php echo $stats['conversations']; ?></h4>
                    <p>Total Conversations</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card text-center">
                    <i class="fas fa-calendar-day fa-2x mb-2"></i>
                    <h4><?php echo $stats['today']; ?></h4>
                    <p>Today's Conversations</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Add Knowledge -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-plus"></i> Add Knowledge</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="add_knowledge">
                            <div class="mb-3">
                                <label class="form-label">Trigger Phrase:</label>
                                <input type="text" name="trigger" class="form-control" placeholder="What users might ask" required>
                                <small class="text-muted">Example: "oil change", "brake service", "how much"</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Bot Response:</label>
                                <textarea name="response" class="form-control" rows="3" placeholder="How the bot should reply" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Add Knowledge
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-cogs"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" onsubmit="return confirm('Are you sure you want to clear all chat history?')">
                            <input type="hidden" name="action" value="clear_history">
                            <button type="submit" class="btn btn-warning mb-2">
                                <i class="fas fa-trash"></i> Clear Chat History
                            </button>
                        </form>
                        <a href="../train.php" class="btn btn-info">
                            <i class="fas fa-graduation-cap"></i> Advanced Training
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Knowledge Base -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-database"></i> Knowledge Base</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $result = $conn->query("SELECT * FROM chat_knowledge ORDER BY id DESC");
                        if ($result->num_rows > 0):
                        ?>
                            <div class="row">
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="knowledge-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <strong>Trigger:</strong> <?php echo htmlspecialchars($row['trigger1']); ?><br>
                                                    <strong>Response:</strong> <?php echo htmlspecialchars($row['response']); ?>
                                                </div>
                                                <form method="post" style="display: inline;" onsubmit="return confirm('Delete this knowledge?')">
                                                    <input type="hidden" name="action" value="delete_knowledge">
                                                    <input type="hidden" name="knowledge_id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No knowledge entries found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Chat History -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> Recent Chat History</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $result = $conn->query("SELECT * FROM chat_history ORDER BY created_at DESC LIMIT 20");
                        if ($result->num_rows > 0):
                        ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <div class="chat-history-item">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="user-message p-2 rounded">
                                                <strong>User:</strong> <?php echo htmlspecialchars($row['user_message']); ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="bot-message p-2 rounded">
                                                <strong>Bot:</strong> <?php echo htmlspecialchars($row['bot_reply']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <small class="text-muted mt-2 d-block">
                                        <?php echo date('M j, Y g:i A', strtotime($row['created_at'])); ?>
                                    </small>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No chat history found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 