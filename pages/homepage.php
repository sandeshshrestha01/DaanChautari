<?php
$extra_css = ['home.css'];
include_once "../includes/header.php";
?>
    <!-- Navigation Header -->
    

    <!-- Main Content Scaffolding -->
    <main>
        <!-- 1. HERO SECTION -->
        <section class="home">
            <div class="hero-inner">
                <!-- LEFT: Text Content -->
                <div class="home-text">
                      <div class="eyebrow">
              <span class="dot"></span> A community goods-sharing platform
            </div>
                
                    <h1 class="hero-title">Welcome to <span class="brand">Dan Chautari</span></h1>
                    <h2 class="hero-tagline">Sahayogko Chautari, <span>Aashako Yatra</span></h2>
                    <p class="hero-desc">
                        Daan Garaun, Sahara Banaun. We connect people who have goods to give —
                        food, clothes, books, and daily essentials — with the families and
                        communities who need them most. No money changes hands, only kindness.
                    </p>
                    <div class="home-buttons">
                        <a href="donate.php" class="primary-btn hero-primary-btn">Donate</a>
                        <a href="../pages/volunteer.php" class="secondary-btn hero-secondary-btn">Become a Volunteer</a>
                    </div>
                </div>

                <!-- RIGHT: Flow Diagram -->
                <div class="hero-diagram">
                    <svg class="flow-svg" viewBox="0 0 340 220" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <!-- Dotted arc: Donor to Community (top arc) -->
                        <path d="M 80 55 Q 200 0 280 55" stroke="#f9a825" stroke-width="2" stroke-dasharray="6 5" fill="none"/>
                        <!-- Dotted line: Donor to Chautari Hub -->
                        <path d="M 80 65 Q 100 130 140 145" stroke="#f9a825" stroke-width="2" stroke-dasharray="6 5" fill="none"/>
                        <!-- Dotted line: Chautari Hub to Community -->
                        <path d="M 195 145 Q 240 130 275 68" stroke="#f9a825" stroke-width="2" stroke-dasharray="6 5" fill="none"/>

                        <!-- Donor node -->
                        <circle cx="80" cy="55" r="28" fill="#f9a825" opacity="0.9"/>
                        <text x="80" y="59" text-anchor="middle" font-size="20" fill="#1b5e20">👤</text>
                        <text x="80" y="100" text-anchor="middle" font-size="12" fill="#ffffff" font-weight="500">Donor</text>

                        <!-- Chautari Hub node (center, slightly lower) -->
                        <circle cx="168" cy="155" r="34" fill="#1b5e20" opacity="0.95"/>
                        <text x="168" y="161" text-anchor="middle" font-size="22" fill="#f9a825">🤝</text>
                        <text x="168" y="202" text-anchor="middle" font-size="12" fill="#ffffff" font-weight="500">Chautari Hub</text>

                        <!-- Community node -->
                        <circle cx="278" cy="55" r="28" fill="#2e7d32" stroke="#f9a825" stroke-width="2"/>
                        <text x="278" y="59" text-anchor="middle" font-size="18" fill="#ffffff">🏘</text>
                        <text x="278" y="100" text-anchor="middle" font-size="12" fill="#ffffff" font-weight="500">Community</text>
                    </svg>
                </div>
            </div>
        </section>

        <!-- 2. STATS BAR SECTION -->
        <section class="stats-section">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number" data-count="2300">0+</div>
                    <div class="stat-label">Items Donated</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" data-count="80">0+</div>
                    <div class="stat-label">Generous Donors</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" data-count="45">0+</div>
                    <div class="stat-label">Active Volunteers</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" data-count="3">0</div>
                    <div class="stat-label">Communities Supported</div>
                </div>
            </div>
        </section>

        <!-- 3. CORE PILLARS SECTION -->
        <section class="section">
            <div class="section-header">
                <h2>How We Help</h2>
                <p>Our efforts center around three developmental pathways to build sustainable communities — all powered by donated goods, not money.</p>
            </div>
            
            <div class="causes-grid" style="max-width: 1100px; margin: 0 auto; text-align: center;">
                <div class="cause-card" style="padding: 30px; border-top: 5px solid var(--primary-green);">
                    <div style="font-size: 40px; margin-bottom: 15px;"> <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
                <path
                  d="M3 5.5c3-1.5 6-1.5 9 0v13c-3-1.5-6-1.5-9 0v-13z"
                  stroke="#2e7d32"
                  stroke-width="1.6"
                  stroke-linejoin="round"
                />
                <path
                  d="M21 5.5c-3-1.5-6-1.5-9 0v13c3-1.5 6-1.5 9 0v-13z"
                  stroke="#2e7d32"
                  stroke-width="1.6"
                  stroke-linejoin="round"
                />
              </svg>
            </div>
                    <h3 style="margin-bottom: 10px; font-size: 20px;">Education Aid</h3>
                    <p>Sponsoring tuition, bags, warm outfits, and text materials for children across high-risk rural schools.</p>
                </div>
                <div class="cause-card" style="padding: 30px; border-top: 5px solid var(--accent-yellow);">
                    <div style="font-size: 40px; margin-bottom: 15px;"><svg width="26" height="26" viewBox="0 0 24 24" fill="none">
                <path
                  d="M12 3l2.5 5 5.5.6-4 3.9 1 5.5-5-2.9-5 2.9 1-5.5-4-3.9 5.5-.6L12 3z"
                  stroke="#f9a825"
                  stroke-width="1.6"
                  stroke-linejoin="round"
                />
              </svg>
            </div>
                    <h3 style="margin-bottom: 10px; font-size: 20px;">Disaster Response</h3>
                    <p>Immediate delivery of survival kits, medical materials, blankets, and foods in flooding or earthquake crises.</p>
                </div>
                <div class="cause-card" style="padding: 30px; border-top: 5px solid var(--dark-green);">
                    <div style="font-size: 40px; margin-bottom: 15px;"><svg width="26" height="26" viewBox="0 0 24 24" fill="none">
                <path
                  d="M4 11h16v2a7 7 0 01-7 7H11a7 7 0 01-7-7v-2z"
                  stroke="#1b5e20"
                  stroke-width="1.6"
                  stroke-linejoin="round"
                />
                <path
                  d="M8 11c-1-3 .5-5 2-6M12 11c-1-4 .5-6 2-7M16 11c-.5-2.5.5-4 1.5-5"
                  stroke="#f3f727ff"
                  stroke-width="1.6"
                  stroke-linecap="round"
                />
              </svg>
            </div>
                    <h3 style="margin-bottom: 10px; font-size: 20px;">Community Kitchens</h3>
                    <p>Ensuring daily warm nourishing lunches are distributed freely for labor workers and low-income families.</p>
                </div>
            </div>
        </section>

        <!-- 4. FEATURED CAUSES SECTION -->
        <section class="section section-bg">
            <div class="section-header">
                <h2>Current Community Needs</h2>
                <p>Every campaign below lists exactly what's needed. Choose a cause and donate the goods yourself — we handle the rest.</p>
            </div>

            <div class="causes-grid">
                <!-- Cause 1 -->
                <div class="cause-card">
                    <div class="need-banner b2">
                        <span class="cause-badge">Urgent</span>
                    </div>
                    <div class="cause-content">
                        <h3>Rural Children Education Support</h3>
                        <p>Help us purchase school supplies, bags, text books, and sponsor tuition for orphans in remote provinces...</p>
                        <div class="cause-progress-wrapper">
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: 30%;"></div>
                            </div>
                            <div class="progress-stats">
                                <span class="raised">Collected: 90 bags (30%)</span>
                                <span class="goal">Goal: 3000 bags</span>
                            </div>
                        </div>
                        <a href="donate.php?cause_id=1" class="primary-btn" style="width: 100%; text-align: center;">Donate towards this Cause</a>
                    </div>
                </div>

                <!-- Cause 2 -->
                <div class="cause-card">
                    <div class="need-banner b3">
                        <span class="cause-badge">Ongoing</span>
                    </div>
                    <div class="cause-content">
                        <h3>Disaster Emergency Relief Fund</h3>
                        <p>Provide swift emergency responses (blankets, food supplies, first-aid packs) to flood-affected families...</p>
                        <div class="cause-progress-wrapper">
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: 32%;"></div>
                            </div>
                            <div class="progress-stats">
                                <span class="raised">Collected: 320 kits (32%)</span>
                                <span class="goal">Goal: 1000 kits</span>
                            </div>
                        </div>
                        <a href="donate.php?cause_id=2" class="primary-btn" style="width: 100%; text-align: center;">Donate towards this Cause</a>
                    </div>
                </div>

                <!-- Cause 3 -->
                <div class="cause-card">
                    <div class="need-banner b2" >
                        <span class="cause-badge">Urgent</span>
                    </div>
                    <div class="cause-content">
                        <h3>Community Kitchen & Food Bank</h3>
                        <p>Running free afternoon warm soup and nutritional meals kitchens daily for daily wage laborers...</p>
                        <div class="cause-progress-wrapper">
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: 28%;"></div>
                            </div>
                            <div class="progress-stats">
                                <span class="raised">Collected: 8500 meals (28%)</span>
                                <span class="goal">Goal: 30,000 meals</span>
                            </div>
                        </div>
                        <a href="donate.php?cause_id=3" class="primary-btn" style="width: 100%; text-align: center;">Donate towards this Cause</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- 5. VOLUNTEER CTA -->
        <section class="section">
            <div class="volunteer-cta">
                <div class="volunteer-cta-text">
                    <h2>Ready to Make an Impact?</h2>
                    <p>If you cannot contribute financially, donate your time! Join our dedicated panel of network volunteers helping coordinate relief drives and camp assemblies on the ground.</p>
                </div>
                <div>
                    <a href="../pages/volunteer.php" class="secondary-btn">Register as a Volunteer</a>
                </div>
            </div>
        </section>
    </main>
<script>
    const counters = document.querySelectorAll(".stat-number");

    counters.forEach((counter) => {
        const target = +counter.getAttribute("data-count");
        let current = 0;
        const increment = target > 100 ? 50 : 1;
        const step = setInterval(() => {
            current += increment;
            counter.innerText = current.toLocaleString() + "+";
            if (current >= target) {
                clearInterval(step);
                counter.innerText = target.toLocaleString() + "+";
            }
        }, 20);
    });
</script>   

<?php
include_once "../includes/footer.php";
?>
