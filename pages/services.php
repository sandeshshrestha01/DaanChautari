<?php
$extra_css  = ['home.css', 'services.css'];
$page_title = 'Our Services';
include_once "../includes/header.php";
?>

<!-- ─── PAGE HERO ─── -->
<section class="page-hero">
    <div class="page-hero-inner">
        <div class="eyebrow"><span class="dot"></span> What We Do</div>
        <h1>Our <span>Services</span></h1>
        <p>From emergency relief to daily community kitchens — discover the full range of ways Dan Chautari is making a difference across Nepal, one donated good at a time.</p>
    </div>
</section>

<!-- ─── CORE SERVICES ─── -->
<section class="section">
    <div class="section-header">
        <h2>What We Offer</h2>
        <p>Every service is powered entirely by donated goods and volunteer time — no money, only community kindness.</p>
    </div>
    <div class="services-grid">

        <div class="service-card">
            <div class="service-icon-wrap" style="background:linear-gradient(135deg,#e8f5e9,#c8e6c9);">
                <span>📚</span>
            </div>
            <div class="service-body">
                <h3>Education Aid Program</h3>
                <p>We collect school bags, stationery, textbooks, and uniforms from donors and distribute them to children in remote, under-resourced schools across Nepal's rural provinces.</p>
                <ul class="service-list">
                    <li>School supply collection &amp; distribution</li>
                    <li>Tuition sponsorship coordination</li>
                    <li>Book &amp; stationery drives</li>
                    <li>Uniform donation campaigns</li>
                </ul>
                <a href="donate.php?cause_id=1" class="service-cta-btn">Donate for Education →</a>
            </div>
        </div>

        <div class="service-card">
            <div class="service-icon-wrap" style="background:linear-gradient(135deg,#fff3e0,#ffe0b2);">
                <span>⛑️</span>
            </div>
            <div class="service-body">
                <h3>Disaster Relief Response</h3>
                <p>When floods, earthquakes, or landslides strike, we mobilize immediately — collecting and distributing emergency kits, blankets, food packets, and medical essentials to affected families.</p>
                <ul class="service-list">
                    <li>Emergency survival kit assembly</li>
                    <li>Blanket &amp; warm clothing drives</li>
                    <li>First-aid supply collection</li>
                    <li>Food parcel distribution</li>
                </ul>
                <a href="donate.php?cause_id=2" class="service-cta-btn">Support Disaster Relief →</a>
            </div>
        </div>

        <div class="service-card">
            <div class="service-icon-wrap" style="background:linear-gradient(135deg,#e8f5e9,#a5d6a7);">
                <span>🍲</span>
            </div>
            <div class="service-body">
                <h3>Community Kitchen &amp; Food Bank</h3>
                <p>Our community kitchens run daily, providing free warm meals to daily wage workers, street vendors, and low-income families who cannot afford nutritious food regularly.</p>
                <ul class="service-list">
                    <li>Daily free hot meal service</li>
                    <li>Grocery &amp; dry goods bank</li>
                    <li>Festival food drives</li>
                    <li>Nutrition package assembly</li>
                </ul>
                <a href="donate.php?cause_id=3" class="service-cta-btn">Donate Food Items →</a>
            </div>
        </div>

        <div class="service-card">
            <div class="service-icon-wrap" style="background:linear-gradient(135deg,#e3f2fd,#bbdefb);">
                <span>👗</span>
            </div>
            <div class="service-body">
                <h3>Clothing &amp; Essential Goods Drive</h3>
                <p>Gently used clothing, shoes, household items, and hygiene products are collected, sanitized, sorted, and distributed — with dignity — to families who need them most.</p>
                <ul class="service-list">
                    <li>Winter clothing collection</li>
                    <li>Children's apparel drives</li>
                    <li>Household goods redistribution</li>
                    <li>Hygiene kit assembly</li>
                </ul>
                <a href="donate.php" class="service-cta-btn">Donate Clothing →</a>
            </div>
        </div>

        <div class="service-card">
            <div class="service-icon-wrap" style="background:linear-gradient(135deg,#fce4ec,#f8bbd0);">
                <span>🧑‍🤝‍🧑</span>
            </div>
            <div class="service-body">
                <h3>Volunteer Network</h3>
                <p>Our backbone — a 45+ strong volunteer force that coordinates pickups, drop-offs, packing, sorting, and on-ground logistics to make every donation reach its destination safely.</p>
                <ul class="service-list">
                    <li>Pickup &amp; delivery coordination</li>
                    <li>Community event organization</li>
                    <li>Donor relationship management</li>
                    <li>Ground-level distribution</li>
                </ul>
                <a href="../auth/signup.php" class="service-cta-btn">Join as Volunteer →</a>
            </div>
        </div>

        <div class="service-card">
            <div class="service-icon-wrap" style="background:linear-gradient(135deg,#f3e5f5,#e1bee7);">
                <span>📊</span>
            </div>
            <div class="service-body">
                <h3>Transparent Donation Tracking</h3>
                <p>Every donation listed on our platform is tracked from submission to delivery. Donors can view their impact dashboard, see photos of their donated goods, and track distribution status in real time.</p>
                <ul class="service-list">
                    <li>Real-time donation status updates</li>
                    <li>Photo verification system</li>
                    <li>Donor impact dashboard</li>
                    <li>Community need reporting</li>
                </ul>
                <a href="../auth/signup.php" class="service-cta-btn">View Your Impact →</a>
            </div>
        </div>

    </div>
</section>

<!-- ─── HOW IT WORKS ─── -->
<section class="section section-bg">
    <div class="section-header">
        <h2>How The Process Works</h2>
        <p>From listing a donation to delivery at a doorstep — here's exactly what happens behind the scenes.</p>
    </div>
    <div class="process-steps">
        <div class="process-step">
            <div class="step-num">01</div>
            <div class="step-icon">📝</div>
            <h4>List Your Donation</h4>
            <p>Sign up as a donor and list your goods — food, clothing, books, or essentials — with a photo and description.</p>
        </div>
        <div class="process-arrow">→</div>
        <div class="process-step">
            <div class="step-num">02</div>
            <div class="step-icon">🔍</div>
            <h4>We Review &amp; Match</h4>
            <p>Our team reviews the listing and matches it with the highest-priority community need in your area.</p>
        </div>
        <div class="process-arrow">→</div>
        <div class="process-step">
            <div class="step-num">03</div>
            <div class="step-icon">🚐</div>
            <h4>Volunteer Collects</h4>
            <p>A verified volunteer arranges pickup from your location at a time that works for you — completely free.</p>
        </div>
        <div class="process-arrow">→</div>
        <div class="process-step">
            <div class="step-num">04</div>
            <div class="step-icon">🏠</div>
            <h4>Community Receives</h4>
            <p>Goods are delivered directly to the recipient family or community hub — and you get a photo confirmation.</p>
        </div>
    </div>
</section>

<!-- ─── CTA ─── -->
<section class="section">
    <div class="volunteer-cta">
        <div class="volunteer-cta-text">
            <h2>Ready to Start Donating?</h2>
            <p>It takes less than 5 minutes to list your first donation. Join 80+ donors who are already changing lives across Nepal.</p>
        </div>
        <div>
            <a href="donate.php" class="secondary-btn" style="padding:14px 32px;font-size:15px;">Start Donating Now →</a>
        </div>
    </div>
</section>


<?php include_once "../includes/footer.php"; ?>
