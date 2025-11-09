<?php
// Handle trailing slash redirects - add this at the very top of each file (after <?php)
if (substr($_SERVER['REQUEST_URI'], -1) === '/') {
    // Remove trailing slash and redirect immediately
    $clean_uri = rtrim($_SERVER['REQUEST_URI'], '/');
    
    // Force redirect with 301 status
    http_response_code(301);
    header("Location: $clean_uri");
    header("HTTP/1.1 301 Moved Permanently");
    exit();
}

require_once 'includes/config.php';

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $message_text = trim($_POST['message'] ?? '');
    
    // Basic validation
    if (empty($email)) {
        $error = 'Please enter your contact email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (empty($message_text)) {
        $error = 'Please enter your message or bug report.';
    } else {
        // Insert into database
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $conn->prepare("INSERT INTO contact_messages (email, message, created_at, ip_address, user_agent) VALUES (?, ?, NOW(), ?, ?)");
        $stmt->bind_param("ssss", $email, $message_text, $ip_address, $user_agent);
        
        if ($stmt->execute()) {
            $message = 'Thank you! Your message has been sent successfully.';
            // Clear form data after successful submission
            $email = '';
            $message_text = '';
        } else {
            $error = 'Sorry, there was an error sending your message. Please try again.';
        }
        $stmt->close();
    }
}

include 'includes/header.php';
include 'includes/navigation.php';
?>
<main>
    <section>
        <div class="splash-container" id="splash" hx-ext="include-vals">
            <div id="splash_wrapper" class="splash" style="max-width: 1680px;">
                <h1 class="splash-head hide-after-request" id="bigmessage" style="padding-bottom:0px;">
                   Contact Us
                </h1>
            </div>
        </div>
    </section>
    
    <div id="main_body_hide" class="hide-after-request">
        <section>
            <div class="content-wrapper">
                <div class="content main__content__container">
                    <div class="content u-mtop">
                        <div class="main__content__container">
                            <div class="pure-g">
                                <div class="pure-u-1 pure-u-sm-3-24"></div>
                                <div class="l-box pure-u-1 pure-u-sm-18-24">
                                    <div class="page-content-box" >
                                        <?php if ($message): ?>
                                            <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #c3e6cb;">
                                                <?php echo htmlspecialchars($message); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($error): ?>
                                            <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #f5c6cb;">
                                                <?php echo htmlspecialchars($error); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <form method="POST" action="" style="max-width: 600px; margin: 0 auto;">
                                            <div class="form-group" style="margin-bottom: 1.5rem;">
                                                <label for="email" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333;">
                                                    Your Contact Email *
                                                </label>
                                                <input 
                                                    type="email" 
                                                    id="email" 
                                                    name="email" 
                                                    value="<?php echo htmlspecialchars($email ?? ''); ?>"
                                                    required 
                                                    style="width: 100%; padding: 0.75rem; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem; transition: border-color 0.3s ease;"
                                                    placeholder="Enter your email address"
                                                >
                                            </div>
                                            
                                            <div class="form-group" style="margin-bottom: 1.5rem;">
                                                <label for="message" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333;">
                                                    Message or Bug Report *
                                                </label>
                                                <textarea 
                                                    id="message" 
                                                    name="message" 
                                                    rows="6" 
                                                    required 
                                                    style="width: 100%; padding: 0.75rem; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem; resize: vertical; transition: border-color 0.3s ease;"
                                                    placeholder="Please describe your message or report any bugs you've encountered..."
                                                ><?php echo htmlspecialchars($message_text ?? ''); ?></textarea>
                                            </div>
                                            <p style=" margin-bottom: 2rem; color: #666; font-size: 1.1rem;">
                                            We are here to answer any questions or inquiries that you may have. Reach out to us and we will respond as soon as possible. Saw a bug? Something is wrong with the video? Use the form to report!
                                        </p>
                                            <div class="form-group" style="text-align: center;">
                                                <button 
                                                    type="submit" 
                                                    style="background: #2172f6; color: white; border: none; padding: 0.75rem 2rem; border-radius: 8px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: background-color 0.3s ease;"
                                                    onmouseover="this.style.background='#0056b3'"
                                                    onmouseout="this.style.background='#007bff'"
                                                >
                                                    Send Message
                                                </button>
                                            </div>
                                        </form>
                                        
                                    </div>
                                </div>
                                <div class="pure-u-1 pure-u-sm-3-24"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
