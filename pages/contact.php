<?php
$extra_css  = ['home.css', 'contact.css'];
$page_title = 'Contact Us';
require __DIR__ . '/../includes/header.php';
//Import PHPMailer classes into the global namespace
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


$sent = false;
$error = '';

// Handle form submit (basic mail or just show success for now)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $type = $_POST['type'] ?? '';

    if ($name && $email && $subject && $message) {
        // You can add mail() or a DB insert here
        // For now just show success


//Load Composer's autoloader (created by composer, not included with PHPMailer)
require __DIR__ . '/../includes/PHP mailer/Exception.php';
require __DIR__ . '/../includes/PHP mailer/PHPMailer.php';
require __DIR__ . '/../includes/PHP mailer/SMTP.php';

//Create an instance; passing `true` enables exceptions
$mail = new PHPMailer(true);

try {
    //Server settings
    $mail->SMTPDebug = SMTP::DEBUG_OFF;                    //Enable verbose debug output
    $mail->isSMTP();                                            //Send using SMTP
    $mail->Host       = 'smtp.gmail.com';                     //Set the SMTP server to send through
    $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
    $mail->Username   = 'sandeshcrest7@gmail.com';                     //SMTP username
    $mail->Password   = 'lqbj trig wtru bbep';                               //SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
    $mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

    //Recipients
    $mail->setFrom('sandeshcrest7@gmail.com', $name);
    $mail->addAddress('sandeshshresth81@gmail.com', 'Dan chautari'); //Add a recipient
    // Reply goes directly to the user who submitted the form
    $mail->addReplyTo($email, $name);

   
    $mail->isHTML(true);                                  //Set email format to HTML
    $mail->Subject = $subject;
    $mail->Body = "
<h2>New Contact Message</h2>

<b>Name:</b> {$name}<br>
<b>Email:</b> {$email}<br>
<b>Subject:</b> {$subject}<br><br>
<b>Inquiry Type:</b> {$type}<br><br>

<b>Message:</b><br>
" . nl2br(htmlspecialchars($message));
    

   if ($mail->send()) {
    $sent = true;
}
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
      
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>

<!-- ─── PAGE HERO ─── -->
<section class="page-hero">
    <div class="page-hero-inner">
        <div class="eyebrow"><span class="dot"></span> Get In Touch</div>
        <h1>Contact <span>Us</span></h1>
        <p>Have a question, want to partner with us, or need help with a donation? We'd love to hear from you — our team responds within 24 hours.</p>
    </div>
</section>

<!-- ─── CONTACT CONTENT ─── -->
<section class="section">
    <div class="contact-wrapper">

        <!-- LEFT: Info Cards -->
        <div class="contact-info-col">
            <h2 class="contact-heading">Let's Talk</h2>
            <p class="contact-subtext">Reach out through any channel below, or use the form and we'll get back to you as quickly as possible.</p>

            <div class="contact-cards">
                <div class="contact-info-card">
                    <div class="ci-icon" style="background:#e8f5e9;">📍</div>
                    <div>
                        <h4>Our Office</h4>
                        <p>Kupondole, Lalitpur<br>Bagmati Province, Nepal</p>
                    </div>
                </div>
                <div class="contact-info-card">
                    <div class="ci-icon" style="background:#fff3e0;">📞</div>
                    <div>
                        <h4>Phone</h4>
                        <p>+977-1-5555555<br>+977-9800000000</p>
                    </div>
                </div>
                <div class="contact-info-card">
                    <div class="ci-icon" style="background:#e3f2fd;">✉️</div>
                    <div>
                        <h4>Email</h4>
                        <p>info@danchautari.org<br>support@danchautari.org</p>
                    </div>
                </div>
                <div class="contact-info-card">
                    <div class="ci-icon" style="background:#f3e5f5;">🕒</div>
                    <div>
                        <h4>Office Hours</h4>
                        <p>Sunday – Friday<br>9:00 AM – 5:00 PM</p>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="contact-quick-links">
                <p style="font-weight:700;color:#1a2618;margin-bottom:12px;">Quick Actions</p>
                <a href="donate.php" class="cql-btn">🤲 Make a Donation</a>
                <a href="../auth/signup.php" class="cql-btn">🙏 Register as Recipient</a>
                <a href="../auth/signup.php" class="cql-btn">🧑‍🤝‍🧑 Join as Volunteer</a>
            </div>
        </div>

        <!-- RIGHT: Contact Form -->
        <div class="contact-form-col">
            <?php if ($sent): ?>
                <div class="contact-success">
                    <div class="success-icon">✅</div>
                    <h3>Message Sent!</h3>
                    <p>Thank you for reaching out. Our team will respond to your message within 24 hours.</p>
                    <a href="contact.php" class="primary-btn" style="margin-top:20px;">Send Another Message</a>
                </div>
            <?php else: ?>
                <form method="POST" action="contact.php" class="contact-form">
                    <h3>Send Us a Message</h3>
                    <?php if ($error): ?>
                        <div class="form-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <div class="cf-row">
                        <div class="cf-group">
                            <label for="cf_name">Your Name <span class="req">*</span></label>
                            <input type="text" id="cf_name" name="name" placeholder="Sandesh Shrestha" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                        </div>
                        <div class="cf-group">
                            <label for="cf_email">Email Address <span class="req">*</span></label>
                            <input type="email" id="cf_email" name="email" placeholder="you@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="cf-group">
                        <label for="cf_subject">Subject <span class="req">*</span></label>
                        <input type="text" id="cf_subject" name="subject" placeholder="e.g. Partnership Inquiry" required value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
                    </div>

                    <div class="cf-group">
                        <label for="cf_type">Inquiry Type</label>
                        <select id="cf_type" name="type">
                            <option value="">Select a category…</option>
                            <option value="donation">Donation Help</option>
                            <option value="volunteer">Volunteering</option>
                            <option value="partnership">Partnership / NGO</option>
                            <option value="media">Media / Press</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="cf-group">
                        <label for="cf_message">Your Message <span class="req">*</span></label>
                        <textarea id="cf_message" name="message" rows="5" placeholder="Write your message here…" required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="primary-btn" style="width:100%;justify-content:center;padding:13px;">Send Message →</button>
                </form>
            <?php endif; ?>
        </div>

    </div>
</section>

<!-- ─── MAP / COMMUNITY PRESENCE ─── -->
<section class="section section-bg">
    <div class="section-header">
        <h2>Our Community Reach</h2>
        <p>We currently serve these areas across Nepal — with plans to expand to every district.</p>
    </div>
    <div class="reach-grid">
        <div class="reach-card">
            <div class="reach-flag">🏙️</div>
            <h4>Kathmandu Valley</h4>
            <p>Our primary base — active donation collections from Kathmandu, Lalitpur, and Bhaktapur.</p>
            <span class="reach-badge active">Active</span>
        </div>
        <div class="reach-card">
            <div class="reach-flag">🏞️</div>
            <h4>Chitwan</h4>
            <p>Partnering with local NGOs to reach flood-affected families and migrant workers in the Terai region.</p>
            <span class="reach-badge active">Active</span>
        </div>
        <div class="reach-card">
            <div class="reach-flag">🏔️</div>
            <h4>Pokhara &amp; Gandaki</h4>
            <p>Education aid and disaster relief distribution in collaboration with local school networks.</p>
            <span class="reach-badge active">Active</span>
        </div>
        <div class="reach-card">
            <div class="reach-flag">🌄</div>
            <h4>Eastern Hills</h4>
            <p>Expansion in progress — targeting Dhankuta and Taplejung districts for clothing &amp; food drives.</p>
            <span class="reach-badge soon">Coming Soon</span>
        </div>
    </div>
</section>


<?php require __DIR__ . '/../includes/footer.php'; ?>
