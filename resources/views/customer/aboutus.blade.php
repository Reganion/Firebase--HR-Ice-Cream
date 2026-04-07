<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | H&R Ice Cream</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Inter", sans-serif;
        }

        body {
            background: #ffffff;
            color: #333;
        }

        /* NAVBAR */
        .navbar {
            position: relative;
            display: flex;
            align-items: center;
            padding: 25px 80px;
        }

        /* LOGO */
        .logo img {
            height: 45px;
            width: auto;
            display: block;
        }

        /* CENTER NAV LINKS */
        .nav-links {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 40px;
        }

        /* NAV LINKS */
        .nav-links a {
            position: relative;
            text-decoration: none;
            color: #333;
            font-size: 16px;
            font-weight: 700;
            padding-bottom: 6px;
        }

        /* UNDERLINE */
        .nav-links a::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: 0;
            width: 0%;
            height: 2px;
            background-color: #E3001B;
            transition: width 0.3s ease;
        }

        /* HOVER */
        .nav-links a:hover {
            color: #E3001B;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        /* ACTIVE LINK */
        .nav-links a.active {
            color: #E3001B;
        }

        .nav-links a.active::after {
            width: 100%;
        }

        /* MAIN SECTION */
        .about-container {
            display: flex;
            align-items: flex-start;
            padding: 60px 80px;
            gap: 80px;
        }


        /* LEFT */
        .about-left {
            flex: 1;
        }

        .about-left h1 {
            font-size: 40px;
            margin-bottom: 20px;
        }

        .about-left h1 span {
            color: #e60023;
        }

        .image-box {
            width: 100%;
            height: 400px;
            background: #f4f6f8;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }


        .image-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        /* RIGHT */
        .about-right {
            flex: 1;
        }

        .about-right h2 {
            font-size: 28px;
            margin-bottom: 20px;
        }

        .about-right p {
            line-height: 1.8;
            margin-bottom: 20px;
            color: #555;
        }

        /* STATS */
        .stats {
            display: flex;
            gap: 20px;
            margin: 30px 0;
        }

        .stat-box {
            background: #f7f7f7;
            padding: 25px;
            border-radius: 15px;
            width: 150px;
            text-align: center;
        }

        .stat-box h3 {
            font-size: 32px;
            margin-bottom: 5px;
            color: #111;
        }

        .stat-box span {
            font-size: 14px;
            color: #777;
        }

        /* BUTTON */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            border: 2px solid #e60023;
            border-radius: 50px;
            color: #e60023;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: #e60023;
            color: #fff;
            transform: translateY(-2px);
        }


        @media (max-width: 900px) {
            .navbar {
                justify-content: space-between;
            }

            .nav-links {
                position: static;
                transform: none;
                gap: 20px;
            }

            .about-container {
                flex-direction: column;
                padding: 40px 30px;
            }
        }

        /* EXPLORE OVERLAY */
        .explore-overlay {
            position: fixed;
            inset: 0;
            background: #fff;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.4s ease, visibility 0.4s ease;
            overflow-y: auto;
        }

        .explore-overlay.active {
            opacity: 1;
            visibility: visible;
        }


        /* HEADER */
        .explore-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 80px;
        }

        .explore-header img {
            height: 45px;
        }

        .close-btn {
            font-size: 26px;
            cursor: pointer;
            color: #777;
        }

        /* CONTENT */

        .explore-content {
            display: flex;
            gap: 80px;
            padding: 40px 80px 80px;
            transform: translateY(30px);
            opacity: 0;
            transition: all 0.6s ease;
        }

        .explore-overlay.active .explore-content {
            transform: translateY(0);
            opacity: 1;
        }

        /* LEFT IMAGE */
        .explore-image {
            flex: 1;
        }

        .explore-image img {
            width: 100%;
            border-radius: 18px;
            object-fit: cover;
            transform: scale(0.95);
            transition: transform 0.8s ease;
        }

        .explore-overlay.active .explore-image img {
            transform: scale(1);
        }


        /* RIGHT TEXT */
        .explore-text {
            flex: 1;
            color: #555;
            font-size: 15px;
            line-height: 1.9;
        }

        .explore-text p {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeUp 0.6s ease forwards;
            margin-bottom: 10px;
        }

        .explore-text p:nth-child(1) {
            animation-delay: 0.2s;
        }

        .explore-text p:nth-child(2) {
            animation-delay: 0.35s;
        }

        .explore-text p:nth-child(3) {
            animation-delay: 0.5s;
        }

        .explore-text p:nth-child(4) {
            animation-delay: 0.65s;
        }

        .explore-overlay.active .explore-text p {
            animation-play-state: running;
        }

        @keyframes fadeUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
/* HIDE SCROLLBAR (EXPLORE OVERLAY ONLY) */
.explore-overlay {
    scrollbar-width: none;          /* Firefox */
    -ms-overflow-style: none;       /* IE & Edge */
}

.explore-overlay::-webkit-scrollbar {
    display: none;                  /* Chrome, Safari */
}

        .close-btn {
            font-size: 26px;
            cursor: pointer;
            color: #777;
            transition: transform 0.3s ease, color 0.3s ease;
        }

        .close-btn:hover {
            transform: rotate(90deg);
            color: #E3001B;
        }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .explore-content {
                flex-direction: column;
                padding: 30px;
            }

            .explore-header {
                padding: 20px 30px;
            }
        }
    </style>
</head>

<body>

    <!-- NAVBAR -->
    <header class="navbar">
        <div class="logo">
            <img src="{{ asset('img/logo.png') }}" alt="H&R Ice Cream Logo">
        </div>

        <nav class="nav-links">
            <a href="{{ url('/') }}">Home</a>
            <a href="{{ url('/about') }}" class="active">About Us</a>
        </nav>
    </header>

    <!-- ABOUT SECTION -->
    <section class="about-container">

        <!-- LEFT -->
        <div class="about-left">
            <h1>About <span>us</span></h1>

            <div class="image-box">
                <img src="{{ asset('img/ice_cream_illustration.png') }}" alt="Ice Cream Illustration">
            </div>
        </div>

        <!-- RIGHT -->
        <div class="about-right">
            <h2>Who we are</h2>

            <p>
                H&R Ice Cream began on February 26, 2021, as a small home-based business
                selling homemade Filipino-style sorbetes through suroy-suroy within
                the community. Its rich flavor, creaminess, and affordability quickly
                earned loyal customers.
            </p>

            <p>
                As demand increased, the business expanded online through the
                Ice Cream sa Lapu-Lapu Facebook page. From selling just one gallon,
                daily production grew to around eight gallons, generating
                approximately ₱15,000–₱20,000 monthly.
            </p>

            <p>
                Today, H&R Ice Cream is a trusted local brand known for clean,
                delicious, high-quality homemade ice cream—bringing joy to families
                and events while preserving the tradition of classic Filipino sorbetes.
            </p>

            <div class="stats">
                <div class="stat-box">
                    <h3>4+</h3>
                    <span>Years in retail</span>
                </div>

                <div class="stat-box">
                    <h3>10+</h3>
                    <span>Flavors</span>
                </div>

                <div class="stat-box">
                    <h3>10+</h3>
                    <span>Partners</span>
                </div>
            </div>

            <a href="javascript:void(0)" class="btn" onclick="openExplore()">
                Explore more →
            </a>

        </div>

    </section>
    <!-- EXPLORE OVERLAY -->
    <div class="explore-overlay" id="exploreOverlay">

        <div class="explore-header">
            <img src="{{ asset('img/logo.png') }}" alt="H&R Ice Cream Logo">
            <div class="close-btn" onclick="closeExplore()">✕</div>
        </div>

        <div class="explore-content">

            <!-- LEFT IMAGE -->
            <div class="explore-image">
                <img src="{{ asset('img/explore_photo.jpg') }}" alt="H&R Ice Cream Team">
            </div>

            <!-- RIGHT TEXT -->
            <div class="explore-text">
                <p>
                    H&R Ice Cream was founded on February 26, 2021 the owner is Harvey Tampus, as a small home-based
                    venture with just one gallon of homemade ice cream. The owner began by selling through
                    suroy-suroy (roaming) around the community, offering simple yet delicious Filipino-style sorbetes.
                    Despite the limited supply, the ice cream quickly gained attention for its rich flavor, creaminess,
                    and affordability.
                </p>

                <p>
                    Customers began to anticipate the seller’s daily rounds, and orders soon became consistent. As
                    demand grew, more people requested advance orders. Recognizing this opportunity, the owner expanded
                    the business online by creating the official Ice Cream sa Lapu-Lapu Facebook page, allowing the
                    business to showcase products, accept bookings, and reach a broader audience
                </p>

                <p>
                    From its humble beginning of just one gallon, H&R Ice Cream gradually increased production. Today,
                    the business regularly prepares 8 gallons of various flavors daily to meet customer demand. Thanks
                    to strong community support, the business achieves a steady monthly income of approximately ₱15,000
                    to ₱20,000.
                </p>

                <p>
                    H&R Ice Cream continues to grow as a trusted local brand, bringing joy, nostalgia, and high-quality
                    homemade ice cream to families and events. With its commitment to cleanliness, deliciousness, and
                    excellent service, the business aims to expand further while honoring the Filipino tradition of
                    classic street sorbetes.
                </p>
            </div>

        </div>
    </div>
<script>
    function openExplore() {
        document.getElementById('exploreOverlay').classList.add('active');
    }

    function closeExplore() {
        const overlay = document.getElementById('exploreOverlay');
        overlay.classList.remove('active');
    }
</script>


</body>

</html>
