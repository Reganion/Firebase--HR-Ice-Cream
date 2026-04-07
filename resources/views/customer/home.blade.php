<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="{{ asset('img/logo.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('assets/css/customer/home.css') }}">
    <title>H&R Ice Cream</title>
    <style>
        .service-phones {
            display: flex;
            justify-content: center;
            align-items: center;
            background-image: url('{{ asset('img/background-cheerful.png') }}');
            background-size: cover;
            background-position: center;
            border-radius: 20px;
            overflow: hidden;
            padding: var(--space-5, 1.25rem);
            min-height: 18rem;
        }
    </style>
</head>

<body>

    <div id="floatingAlert" class="floating-alert"></div>

    <header>
        <span class="material-symbols-outlined menu-toggle" onclick="toggleMenu()">menu</span>

        <div class="logo">
            <img src="{{ asset('img/logo.png') }}" alt="Quinjay Logo" />
        </div>

        <nav id="nav">
            <a href="#home">Home</a>
            <a href="#top-flavors">Our Flavors</a>
            <a href="#service">Services</a>
            <a href="{{ route('customer.about') }}">About Us</a>
            <a href="#contact">Contact</a>
        </nav>

    </header>

    <section class="hero" id="home">
        <div class="hero-text">
            <h1>
                <span class="red">Your scoop</span><br>
                <span class="black">is just a click away!</span>
            </h1>
            <p>Enjoy rich, handcrafted flavors made from quality ingredients and delivered with care. One click unlocks
                a
                world of creamy indulgence—crafted to satisfy every sweet moment.</p>
            <div class="btns">
                <button type="button" class="download-btn">Download App</button>
            </div>
        </div>

        <div class="phones">
            <img src="{{ asset('img/cellphone 1.png') }}" class="phone phone-1" alt="Cellphone 1">
            <img src="{{ asset('img/cellphone 2.png') }}" class="phone phone-2" alt="Cellphone 2">
        </div>

    </section>

    <!-- Top Flavors Section (endless marquee on mobile; grid on desktop) -->
    <section class="flavors" id="top-flavors">
        <h2 class="section-title section-title--center">Our Popular Flavors</h2>

        <div class="flavors-wrapper flavors-marquee"
            style="--flavor-marquee-secs: {{ max(28, min(100, $flavors->count() * 14)) }}">
            <div class="flavors-marquee-inner">
                <div class="flavors-marquee-track">
                    <div class="flavors-container">
                        @foreach ($flavors as $flavor)
                            <div class="flavor-card">
                                <div class="flavor-img-wrap" role="button" tabindex="0"
                                    aria-label="Preview {{ $flavor->name }}">
                                    <img src="{{ asset($flavor->mobile_image) }}" alt="{{ $flavor->name }}"
                                        class="flavor-img" data-full-src="{{ asset($flavor->mobile_image) }}"
                                        data-caption="{{ $flavor->name }}">
                                </div>
                                <div class="flavor-content">
                                    <h3 class="flavor-name">{{ $flavor->name }}</h3>

                                    <div class="flavor-rating">
                                        <img src="{{ asset('img/star.png') }}" alt="Star">
                                        <span class="rating-text">{{ $flavor->rating ?? '0' }}
                                            ({{ $flavor->reviews ?? '0' }} Reviews)</span>
                                    </div>

                                    <p class="flavor-desc">{{ $flavor->description ?? '' }}</p>

                                    <button type="button" class="flavor-btn" data-flavor-id="{{ $flavor->id }}">
                                        Order Now
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flavors-container flavors-container--marquee-clone" aria-hidden="true" inert>
                        @foreach ($flavors as $flavor)
                            <div class="flavor-card">
                                <div class="flavor-img-wrap" tabindex="-1">
                                    <img src="{{ asset($flavor->mobile_image) }}" alt=""
                                        class="flavor-img" data-full-src="{{ asset($flavor->mobile_image) }}"
                                        data-caption="{{ $flavor->name }}">
                                </div>
                                <div class="flavor-content">
                                    <h3 class="flavor-name">{{ $flavor->name }}</h3>

                                    <div class="flavor-rating">
                                        <img src="{{ asset('img/star.png') }}" alt="">
                                        <span class="rating-text">{{ $flavor->rating ?? '0' }}
                                            ({{ $flavor->reviews ?? '0' }} Reviews)</span>
                                    </div>

                                    <p class="flavor-desc">{{ $flavor->description ?? '' }}</p>

                                    <button type="button" class="flavor-btn" data-flavor-id="{{ $flavor->id }}">
                                        Order Now
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>


    <section class="service" id="service">
        <div class="service-phones">
            <img src="{{ asset('img/cheerful.png') }}" alt="Cheerful Frame" />
        </div>

        <div class="service-text">
            <h1>We Provide Best Service for Our Customer</h1>
            <p>Corem ipsum dolor sit amet, consectetur adipiscing elit. Nunc vulputate libero et velit interdum, ac
                aliquet odio mattis.
                Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos.</p>

            <div class="service-features">
                <div class="feature feature--quality">
                    <div class="icon-bg">
                        <span class="material-symbols-outlined" aria-hidden="true">award_star</span>
                    </div>
                    <p>Best Quality</p>
                </div>
                <div class="feature feature--delivery">
                    <div class="icon-bg">
                        <span class="material-symbols-outlined" aria-hidden="true">delivery_truck_speed</span>
                    </div>
                    <p>Home Delivery</p>
                </div>
                <div class="feature feature--booking">
                    <div class="icon-bg">
                        <span class="material-symbols-outlined" aria-hidden="true">local_taxi</span>
                    </div>
                    <p>Pre Booking</p>
                </div>
                <div class="feature feature--order">
                    <div class="icon-bg">
                        <span class="material-symbols-outlined" aria-hidden="true">shopping_bag</span>
                    </div>
                    <p>Easy to Order</p>
                </div>
            </div>

        </div>
    </section>

    <section class="about" id="aboutus">
        <div class="about-container">

            <h2 class="about-title">
                <span>What are our Customer <br>say about us</span>
            </h2>

            <div class="about-boxes-wrapper about-marquee"
                style="--about-marquee-secs: {{ max(25, min(90, $feedbacks->count() * 14)) }}">
                <div class="about-marquee-track">
                    <div class="about-boxes" aria-label="Customer testimonials">
                        @foreach ($feedbacks as $feedback)
                            <div class="testimonial-card">
                                <div class="profile">
                                    <img src="{{ asset($feedback->photo) }}" alt="customer photo" draggable="false">
                                    <div class="profile-info">
                                        <h3>{{ $feedback->customer_name }}</h3>
                                        <div class="stars">{!! str_repeat('★', $feedback->rating) !!}</div>
                                    </div>
                                </div>
                                <p class="testimonial-text">
                                    {{ $feedback->testimonial }}
                                </p>
                                <span
                                    class="date">{{ \Carbon\Carbon::parse($feedback->feedback_date)->format('d M Y') }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="about-boxes about-boxes--marquee-clone" aria-hidden="true">
                        @foreach ($feedbacks as $feedback)
                            <div class="testimonial-card">
                                <div class="profile">
                                    <img src="{{ asset($feedback->photo) }}" alt="" draggable="false">
                                    <div class="profile-info">
                                        <h3>{{ $feedback->customer_name }}</h3>
                                        <div class="stars">{!! str_repeat('★', $feedback->rating) !!}</div>
                                    </div>
                                </div>
                                <p class="testimonial-text">
                                    {{ $feedback->testimonial }}
                                </p>
                                <span
                                    class="date">{{ \Carbon\Carbon::parse($feedback->feedback_date)->format('d M Y') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- Contact Section -->
    <section class="contact" id="contact">
        <div class="contact-container">
            <!-- Contact Form -->
            <div class="contact-form">
                <h2>Contact Us</h2>
                <p>Indulge in our creamy delights! Reach out to us for any questions or to share your sweet experience..
                </p>

                <form>
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" placeholder="Full name">
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" placeholder="example@gmail.com">
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" placeholder="(+63) 9123456789">
                    </div>

                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" placeholder="Your Message"></textarea>
                    </div>

                    <button type="submit" class="submit-btn">Send message</button>
                </form>
            </div>

            <!-- Image -->
            <div class="contact-image">
                <img src="{{ asset('img/Contact.png') }}" alt="Ice Cream Image">
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-container">
            <div class="footer-col">
                <div class="footer-logo">
                    <img src="{{ asset('img/logo.png') }}" alt="Quinjay Logo">
                </div>
                <p>Creating moments of joy, one scoop at a time. Premium ice cream crafted with love and the finest
                    ingredients.</p>
                <div class="social-links">
                    <a href="#"><img src="{{ asset('icons/facebook.png') }}" alt="Facebook"
                            width="24"></a>
                    <a href="#"><img src="{{ asset('icons/twitter.png') }}" alt="Twitter"
                            width="24"></a>
                    <a href="#"><img src="{{ asset('icons/instagram.png') }}" alt="Instagram"
                            width="24"></a>

                </div>
            </div>

            <div class="footer-col">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#top-flavors">Our Flavors</a></li>
                    <li><a href="#service">Services</a></li>
                    <li><a href="#aboutus">About Us</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h3>About us</h3>
                <ul class="footer-links">
                    <li><a href="{{ route('customer.about') }}">Our story</a></li>
                    <li><a href="#service">Services</a></li>
                    <li><a href="#contact">Contact</a></li>
                    <li><a href="#top-flavors">Flavors</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h3>Contact us</h3>
                <ul class="footer-links">
                    <li>123 Sweet Street, Ice Cream City</li>
                    <li>IC 12345</li>
                    <li>(123) 456-7890</li>
                    <li>info@quinjayicecream.com</li>
                </ul>
            </div>
        </div>

        <div class="copyright">
            <p>All copyright &copy; 2025 Reserved</p>
        </div>
    </footer>

    <!-- Image preview lightbox -->
    <div id="imagePreviewModal" class="image-preview-modal" aria-hidden="true">
        <div class="image-preview-overlay"></div>
        <div class="image-preview-content">
            <button type="button" class="image-preview-close" aria-label="Close preview">&times;</button>
            <img src="" alt="" class="image-preview-img">
            <p class="image-preview-caption"></p>
        </div>
    </div>

    <!-- Mobile sticky CTA -->
    <div class="mobile-sticky-cta" role="region" aria-label="Quick actions">
        <a href="#top-flavors" class="mobile-sticky-cta__btn">Browse flavors</a>
        <a href="{{ route('customer.login') }}" class="mobile-sticky-cta__btn mobile-sticky-cta__btn--secondary">Sign in</a>
    </div>

    <!-- Scroll to Top Button -->
    <button type="button" id="scrollToTopBtn" title="Go to top"><img src="{{ asset('icons/arrow-up.png') }}" alt=""
            width="24" height="24"></button>

    <script>
        const nav = document.getElementById('nav');

        function closeMenu() {
            if (!nav) return;
            nav.classList.remove('active');
            document.body.classList.remove('menu-open');
        }

        function toggleMenu() {
            if (!nav) return;
            const isOpen = nav.classList.toggle('active');
            document.body.classList.toggle('menu-open', isOpen);
        }

        document.querySelectorAll('nav a').forEach(link => {
            link.addEventListener('click', closeMenu);
        });

        document.addEventListener('click', (event) => {
            if (!nav || !nav.classList.contains('active')) return;
            const clickedInsideNav = nav.contains(event.target);
            const clickedToggle = event.target.closest('.menu-toggle');
            if (!clickedInsideNav && !clickedToggle) {
                closeMenu();
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 900) {
                closeMenu();
            }
        });

        // Image preview lightbox
        const modal = document.getElementById('imagePreviewModal');
        const modalImg = modal?.querySelector('.image-preview-img');
        const modalCaption = modal?.querySelector('.image-preview-caption');
        const modalClose = modal?.querySelector('.image-preview-close');
        const modalOverlay = modal?.querySelector('.image-preview-overlay');

        function openImagePreview(src, caption) {
            if (!modal || !modalImg) return;
            modalImg.src = src;
            modalImg.alt = caption;
            modalCaption.textContent = caption;
            modal.setAttribute('aria-hidden', 'false');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeImagePreview() {
            if (!modal) return;
            modal.setAttribute('aria-hidden', 'true');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        document.querySelectorAll('.flavor-img-wrap').forEach(wrap => {
            wrap.addEventListener('click', (e) => {
                const img = wrap.querySelector('.flavor-img');
                if (img && (img.dataset.fullSrc || img.src)) {
                    e.preventDefault();
                    openImagePreview(img.dataset.fullSrc || img.src, img.dataset.caption || img.alt);
                }
            });
            wrap.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    wrap.click();
                }
            });
        });

        if (modalClose) modalClose.addEventListener('click', closeImagePreview);
        if (modalOverlay) modalOverlay.addEventListener('click', closeImagePreview);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal?.classList.contains('active')) closeImagePreview();
        });
    </script>

    <script>
        //Get the button
        const scrollToTopBtn = document.getElementById("scrollToTopBtn");

        // Show button after scrolling down 300px
        window.onscroll = function() {
            if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
                scrollToTopBtn.style.display = "flex";
            } else {
                scrollToTopBtn.style.display = "none";
            }
        };

        // Scroll to top when clicked
        scrollToTopBtn.addEventListener("click", () => {
            window.scrollTo({
                top: 0,
                behavior: "smooth"
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const floatingAlert = document.getElementById('floatingAlert');

            function showFloatingAlert(message, type = 'error') {
                floatingAlert.innerHTML = message;
                if (type === 'success') {
                    floatingAlert.style.backgroundColor = '#e6ffe6';
                    floatingAlert.style.color = '#008000';
                    floatingAlert.style.borderColor = '#008000';
                } else {
                    floatingAlert.style.backgroundColor = '#ffe6e6';
                    floatingAlert.style.color = '#E3001B';
                    floatingAlert.style.borderColor = '#E3001B';
                }

                floatingAlert.classList.add('show');

                setTimeout(() => {
                    floatingAlert.classList.remove('show');
                }, 5000); // auto hide after 5s
            }

            // Sign In Errors
            @if ($errors->has('email') || $errors->has('password'))
                let message = `<ul>
            @if ($errors->has('email'))
                <li>{{ $errors->first('email') }}</li>
            @endif
            @if ($errors->has('password'))
                <li>{{ $errors->first('password') }}</li>
            @endif
        </ul>`;
                showFloatingAlert(message);
            @endif

            // Sign Up Errors
            @if ($errors->any() && !($errors->has('email') || $errors->has('password')))
                let message = `<ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>`;
                showFloatingAlert(message);
            @endif

            // Success message
            @if (session('success'))
                showFloatingAlert("{{ session('success') }}", 'success');
            @endif
        });
    </script>
    <script>
        const sections = document.querySelectorAll("section");
        const navLinks = document.querySelectorAll("nav a");

        window.addEventListener("scroll", () => {
            let current = "";

            sections.forEach(section => {
                const sectionTop = section.offsetTop - 120;
                if (scrollY >= sectionTop) {
                    current = section.getAttribute("id");
                }
            });

            navLinks.forEach(link => {
                link.classList.remove("active");
                const href = link.getAttribute("href");
                if (!current || !href || !href.startsWith("#")) {
                    return;
                }
                if (href === "#" + current) {
                    link.classList.add("active");
                }
            });
        });
    </script>
    <script>
        // Smooth scroll with easing (better than default smooth)
        document.querySelectorAll('nav a[href^="#"]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();

                const target = document.querySelector(this.getAttribute('href'));
                if (!target) return;

                const headerOffset = 96;
                const targetPosition = target.offsetTop - headerOffset;

                smoothScroll(targetPosition, 900); // 900ms duration
            });
        });

        function smoothScroll(targetY, duration = 800) {
            const startY = window.pageYOffset;
            const changeY = targetY - startY;
            let startTime = null;

            function animateScroll(currentTime) {
                if (!startTime) startTime = currentTime;
                const time = currentTime - startTime;
                const progress = Math.min(time / duration, 1);

                // Ease-out cubic (smooth & modern)
                const ease = 1 - Math.pow(1 - progress, 3);

                window.scrollTo(0, startY + changeY * ease);

                if (progress < 1) {
                    requestAnimationFrame(animateScroll);
                }
            }

            requestAnimationFrame(animateScroll);
        }
    </script>

</body>

</html>
