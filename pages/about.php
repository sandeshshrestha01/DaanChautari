<?php
$extra_css  = ['home.css', 'about.css'];
$page_title = 'About Us';
include_once "../includes/header.php";
?>

<!-- ─── PAGE HERO ─── -->
<section class="page-hero">
    <div class="page-hero-inner">
        <div class="eyebrow"><span class="dot"></span> Our Story</div>
        <h1>About <span>Dan Chautari</span></h1>
        <p>A community-driven goods-sharing platform born in the heart of Nepal — bridging compassionate donors with families and communities in need.</p>
    </div>
</section>

<!-- ─── MISSION / VISION / VALUES ─── -->
<section class="section">
    <div class="about-mvv-grid">
        <div class="mvv-card green">
            <div class="mvv-icon">🎯</div>
            <h3>Our Mission</h3>
            <p>To eliminate the barrier between surplus and scarcity — connecting generous donors with struggling communities through a transparent, trustworthy, and dignified goods-sharing platform.</p>
        </div>
        <div class="mvv-card yellow">
            <div class="mvv-icon">🔭</div>
            <h3>Our Vision</h3>
            <p>A Nepal where no family goes without basic essentials — where surplus goods find purpose and every act of kindness creates a ripple of lasting community change.</p>
        </div>
        <div class="mvv-card dark">
            <div class="mvv-icon">🌿</div>
            <h3>Our Values</h3>
            <p>Dignity, Transparency, Community, and Sustainability. We believe in the power of collective generosity to transform lives — without politics, without money, only kindness.</p>
        </div>
    </div>
</section>

<!-- ─── OUR STORY ─── -->
<section class="section section-bg">
    <div class="about-story-wrapper">
        <div class="about-story-text">
            <div class="section-header" style="text-align:left; max-width:100%; margin-bottom:32px;">
                <h2 style="display:inline-block;">How It All Began</h2>
            </div>
            <p>Dan Chautari was founded in 2025 by a group of young Nepali students who witnessed a disturbing contrast — surplus goods piling up in urban homes while rural communities struggled for basics. The idea was simple: build a digital <em>chautari</em> (a community resting place) where those with more could meet those with less.</p>
            <p style="margin-top:16px;">Starting with just 12 donors in Kathmandu, the platform has since grown to support over 80 active donors and 3 community clusters across Nepal. We operate entirely on volunteer energy and donated goods — no money changes hands, only kindness.</p>
            <div class="about-story-highlights">
                <div class="highlight-item">
                    <span class="h-num">2025</span>
                    <span class="h-label">Founded in Kathmandu</span>
                </div>
                <div class="highlight-item">
                    <span class="h-num">30+</span>
                    <span class="h-label">Active Donors</span>
                </div>
                <div class="highlight-item">
                    <span class="h-num">200+</span>
                    <span class="h-label">Goods Donated</span>
                </div>
            </div>
        </div>
        <div class="about-story-visual">
            <div class="story-card-stack">
                <div class="story-card sc1">
                    <span>🤲</span>
                    <p>Donor lists goods</p>
                </div>
                <div class="story-card sc2">
                    <span>🤝</span>
                    <p>We coordinate</p>
                </div>
                <div class="story-card sc3">
                    <span>🏘️</span>
                    <p>Community receives</p>
                </div>
                <svg class="flow-connector" viewBox="0 0 120 200" fill="none">
                    <path d="M60 20 Q80 80 60 100 Q40 120 60 180" stroke="#f9a825" stroke-width="2" stroke-dasharray="6 4"/>
                </svg>
            </div>
        </div>
    </div>
</section>

<!-- ─── TEAM SECTION ─── -->
<section class="section">
    <div class="section-header">
        <h2>Meet Our Team</h2>
        <p>Passionate volunteers and organizers driving change from the ground up.</p>
    </div>
    <div class="team-grid">
        <div class="team-card">
            <div class="team-avatar" style="background:linear-gradient(135deg,#2e7d32,#1b5e20);">🧑</div>
            <h4>Sandesh Shrestha</h4>
            <p class="team-role">Founder &amp; Lead Coordinator</p>
            <p class="team-bio">Computer science student turned changemaker. Sandesh started Dan Chautari after noticing how much usable items went to waste in Kathmandu neighborhoods.</p>
        </div>
        <div class="team-card">
            <div class="team-avatar" style="background:linear-gradient(135deg,#f9a825,#e69100);">🧑</div>
            <h4>Ganesh Kafle</h4>
            <p class="team-role">Community Outreach Lead</p>
            <p class="team-bio">Former social worker with 5 years of grassroots experience. Sunita manages all field coordination and community partnerships across 3 districts.</p>
        </div>
        <div class="team-card">
            <div class="team-avatar" style="background:linear-gradient(135deg,#43a047,#2e7d32);">🧑‍💻</div>
            <h4>Subal Upreti</h4>
            <p class="team-role">Technology &amp; Platform Lead</p>
            <p class="team-bio">Full-stack developer passionate about tech for social good. Built and maintains the entire Dan Chautari digital platform pro bono.</p>
        </div>
    </div>
</section>

<!-- ─── CTA ─── -->
<section class="section">
    <div class="volunteer-cta">
        <div class="volunteer-cta-text">
            <h2>Join Our Mission</h2>
            <p>Whether you have goods to give, time to volunteer, or communities to connect — there is a place for you at Dan Chautari.</p>
        </div>
        <div>
            <a href="../auth/signup.php" class="secondary-btn" style="padding:14px 32px;font-size:15px;">Get Involved →</a>
        </div>
    </div>
</section>



<?php include_once "../includes/footer.php"; ?>
