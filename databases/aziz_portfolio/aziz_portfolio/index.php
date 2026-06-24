<?php
// index.php - Main portfolio page
require_once 'config.php';

// Get personal information
$info_sql = "SELECT * FROM personal_info LIMIT 1";
$info_result = $conn->query($info_sql);
$info = $info_result->fetch_assoc();

// Get education
$edu_sql = "SELECT * FROM education ORDER BY display_order";
$edu_result = $conn->query($edu_sql);

// Get experience
$exp_sql = "SELECT * FROM experience ORDER BY display_order";
$exp_result = $conn->query($exp_sql);

// Get skills
$skills_sql = "SELECT * FROM skills ORDER BY display_order";
$skills_result = $conn->query($skills_sql);

// Get projects
$projects_sql = "SELECT * FROM projects ORDER BY display_order";
$projects_result = $conn->query($projects_sql);

// Get social links
$social_sql = "SELECT * FROM social_links ORDER BY display_order";
$social_result = $conn->query($social_sql);

// Get CV from database
$cv_sql = "SELECT file_path FROM resume_files WHERE is_active = 1 ORDER BY id DESC LIMIT 1";
$cv_result = $conn->query($cv_sql);
$cv = $cv_result->fetch_assoc();
$cv_path = $cv ? $cv['file_path'] : '#';
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($info['title']); ?>">
    <title><?php echo htmlspecialchars($info['full_name']); ?> | Portfolio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/typed.js/2.0.12/typed.min.js"></script>
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Poppins', sans-serif;
    }

    body {
        background: #010714;
        color: #fff;
        line-height: 1.6;
        background-image: url('https://images.unsplash.com/photo-1519681393784-d120267933ba?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        position: relative;
    }

    body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(1, 7, 20, 0.85);
        z-index: -1;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 15px;
    }

    .section-title {
        text-align: center;
        margin-bottom: 50px;
    }

    .section-title h2 {
        font-size: 36px;
        font-weight: 700;
        display: inline-block;
        position: relative;
        margin-bottom: 15px;
        color: #fff;
    }

    .section-title h2::before {
        content: '';
        position: absolute;
        left: 50%;
        bottom: -10px;
        width: 50px;
        height: 2px;
        background: #00e5ff;
        transform: translateX(-50%);
    }

    section {
        padding: 100px 0;
    }

    /* Header */
    header {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        padding: 20px 0;
        z-index: 1000;
        transition: all 0.3s ease;
    }

    header.sticky {
        background: rgba(1, 7, 20, 0.95);
        padding: 15px 0;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.5);
    }

    header .container {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

.logo h1 {
    font-size: 22px;
    font-weight: 700;
    background: linear-gradient(45deg, #00e5ff, #8a2be2);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    white-space: nowrap;
}

nav ul {
    display: flex;
    list-style: none;
    gap: 3px;
    margin: 0;
    padding: 0;
    flex-wrap: nowrap;
}

nav ul li {
    margin: 0;
}

nav ul li a {
    color: #fff;
    text-decoration: none;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 40px;
    transition: all 0.3s;
    font-size: 12px;
    letter-spacing: 0.3px;
    position: relative;
    white-space: nowrap;
}

nav ul li a:hover,
nav ul li a.active {
    color: #00e5ff;
    background: rgba(0, 229, 255, 0.1);
}

    .menu-toggle {
        display: none;
        font-size: 24px;
        cursor: pointer;
    }

    /* Hero Section */
    .hero {
        height: 100vh;
        display: flex;
        align-items: center;
        position: relative;
        background: transparent;
    }

    .hero .container {
        position: relative;
        z-index: 1;
    }

    .hero-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .hero-text {
        flex: 1;
        padding-right: 50px;
    }

    /* Hero Badge - NEW */
    .greeting-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(0, 229, 255, 0.15);
        border: 1px solid rgba(0, 229, 255, 0.4);
        padding: 8px 18px;
        border-radius: 50px;
        font-size: 13px;
        color: #00e5ff;
        margin-bottom: 20px;
        backdrop-filter: blur(10px);
        animation: fadeInUp 0.8s ease;
    }

    .greeting-badge i {
        font-size: 14px;
    }

    /* Hero Text Improvements - UPDATED */
    .hero-text h4 {
        font-size: 18px;
        color: #00e5ff;
        letter-spacing: 2px;
        margin-bottom: 15px;
        text-transform: uppercase;
        opacity: 0.9;
    }

    .hero-text h1 {
        font-size: 56px;
        font-weight: 800;
        margin-bottom: 15px;
        background: linear-gradient(135deg, #fff, #00e5ff, #8a2be2);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .hero-text h3 {
        font-size: 28px;
        margin-bottom: 25px;
        color: rgba(255, 255, 255, 0.9);
    }

    .hero-text h3 span {
        color: #00e5ff;
        text-shadow: 0 0 20px rgba(0, 229, 255, 0.5);
        position: relative;
    }

    .hero-text p {
        margin-bottom: 35px;
        opacity: 0.85;
        font-size: 16px;
        line-height: 1.8;
        max-width: 550px;
    }

    .hero-text p strong {
        color: #00e5ff;
    }

    .social-icons {
        display: flex;
        margin-bottom: 30px;
        gap: 10px;
        flex-wrap: wrap;
    }

    .social-icon {
        display: inline-flex;
        justify-content: center;
        align-items: center;
        width: 40px;
        height: 40px;
        background: transparent;
        border: 2px solid #00e5ff;
        border-radius: 50%;
        color: #00e5ff;
        transition: all 0.3s;
        text-decoration: none;
    }

    .social-icon:hover {
        background: #00e5ff;
        color: #010714;
        transform: translateY(-3px);
    }

    .social-icon img {
        width: 20px;
        height: 20px;
    }

    /* More Button Animation - UPDATED */
    .more-btn {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        padding: 14px 38px;
        background: linear-gradient(135deg, #00e5ff, #8a2be2);
        color: #fff;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.4s;
        position: relative;
        overflow: hidden;
        z-index: 1;
        box-shadow: 0 5px 20px rgba(0, 229, 255, 0.3);
    }

    .more-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #8a2be2, #00e5ff);
        transition: all 0.5s;
        z-index: -1;
    }

    .more-btn:hover::before {
        left: 0;
    }

    .more-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 35px rgba(0, 229, 255, 0.5);
        gap: 15px;
    }

    .more-btn i {
        transition: transform 0.3s;
    }

    .more-btn:hover i {
        transform: translateY(3px);
    }

    .hero-image {
        flex: 1;
        display: flex;
        justify-content: center;
        position: relative;
    }

    /* Floating Badges - NEW */
    .floating-badge {
        position: absolute;
        background: rgba(15, 18, 30, 0.9);
        backdrop-filter: blur(15px);
        padding: 12px 20px;
        border-radius: 50px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        font-weight: 500;
        border: 1px solid rgba(0, 229, 255, 0.3);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        z-index: 10;
        animation: floatBadge 3s ease-in-out infinite;
    }

    .floating-badge i {
        font-size: 18px;
        color: #00e5ff;
    }

    .experience-badge {
        top: 20%;
        left: -30px;
        animation-delay: 0s;
    }

    .projects-badge {
        bottom: 20%;
        right: -30px;
        animation-delay: 1.5s;
    }

    @keyframes floatBadge {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }

    .image-container {
        width: 400px;
        height: 400px;
        border-radius: 50%;
        overflow: hidden;
        border: 5px solid rgba(0, 229, 255, 0.3);
        animation: float 3s ease-in-out infinite;
    }

    .image-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-20px); }
    }

    /* About Section */
    .about {
        background: transparent;
    }

    .about-content {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 50px;
        flex-wrap: wrap;
    }

    .about-image {
        flex: 0 1 400px;
    }

    .about-image img {
        width: 100%;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 229, 255, 0.2);
    }

    .about-text {
        flex: 0 1 600px;
    }

    .about-text h3 {
        font-size: 24px;
        color: #00e5ff;
        margin-bottom: 15px;
    }

    .about-text p {
        margin-bottom: 20px;
        opacity: 0.9;
    }

    .personal-info {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin-bottom: 30px;
    }

    .info-item span {
        font-weight: 600;
        color: #00e5ff;
    }

    .info-item a {
        color: #fff;
        text-decoration: none;
    }

    .info-item a:hover {
        color: #00e5ff;
    }

    .download-cv {
        display: inline-block;
        padding: 12px 30px;
        background: linear-gradient(45deg, #00e5ff, #8a2be2);
        color: #fff;
        border-radius: 50px;
        text-decoration: none;
        transition: all 0.3s;
    }

    .download-cv:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 229, 255, 0.4);
    }

    /* Services/Info Cards */
    .services {
        background: transparent;
    }

    .services-content {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
    }

    .service-card {
        background: rgba(3, 11, 39, 0.8);
        backdrop-filter: blur(10px);
        padding: 30px;
        text-align: center;
        border-radius: 10px;
        transition: all 0.3s;
        border: 1px solid rgba(0, 229, 255, 0.1);
    }

    .service-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 10px 20px rgba(0, 229, 255, 0.2);
        border-color: #00e5ff;
    }

    .service-card i {
        font-size: 48px;
        color: #00e5ff;
        margin-bottom: 20px;
    }

    .service-card h3 {
        font-size: 20px;
        margin-bottom: 15px;
    }

    .service-card p {
        font-size: 14px;
        opacity: 0.8;
    }

    .service-card small {
        display: block;
        margin-top: 10px;
        color: #00e5ff;
        font-size: 12px;
    }

    /* Skills Section */
    .skills {
        background: transparent;
    }

    .skills-content {
        max-width: 700px;
        margin: 0 auto;
    }

    .skill-item {
        margin-bottom: 30px;
    }

    .skill-item h4 {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
    }

    .progress-bar {
        width: 100%;
        height: 10px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 5px;
        overflow: hidden;
    }

    .progress {
        height: 100%;
        background: linear-gradient(45deg, #00e5ff, #8a2be2);
        border-radius: 5px;
    }

/* Certifications Stats */
.cert-stats {
    display: flex;
    justify-content: center;
    gap: 40px;
    margin-bottom: 50px;
    flex-wrap: wrap;
}

.stat-item {
    text-align: center;
    background: rgba(3, 11, 39, 0.6);
    backdrop-filter: blur(12px);
    padding: 20px 30px;
    border-radius: 20px;
    border: 1px solid rgba(0, 229, 255, 0.15);
    min-width: 150px;
    transition: all 0.3s;
}

.stat-item:hover {
    transform: translateY(-5px);
    border-color: rgba(0, 229, 255, 0.4);
}

.stat-item i {
    font-size: 32px;
    background: linear-gradient(135deg, #00e5ff, #8a2be2);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    display: block;
    margin-bottom: 10px;
}

.stat-number {
    display: block;
    font-size: 28px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 13px;
    color: rgba(255,255,255,0.6);
}

/* Certifications Filters */
.cert-filters {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-bottom: 40px;
    flex-wrap: wrap;
}

.cert-filters .filter-btn {
    background: rgba(3, 11, 39, 0.6);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(0, 229, 255, 0.2);
    color: #fff;
    padding: 8px 24px;
    border-radius: 40px;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 14px;
    font-weight: 500;
}

.cert-filters .filter-btn:hover,
.cert-filters .filter-btn.active {
    background: linear-gradient(135deg, #00e5ff, #8a2be2);
    color: #010714;
    transform: translateY(-2px);
}

/* Certifications Grid */
.certifications-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 25px;
    margin-top: 20px;
}

.cert-card {
    background: rgba(3, 11, 39, 0.6);
    backdrop-filter: blur(12px);
    border-radius: 20px;
    padding: 25px;
    border: 1px solid rgba(0, 229, 255, 0.15);
    transition: all 0.3s;
    text-align: center;
}

.cert-card:hover {
    transform: translateY(-8px);
    border-color: rgba(0, 229, 255, 0.4);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
}

.cert-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, rgba(0, 229, 255, 0.15), rgba(138, 43, 226, 0.15));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.cert-icon i {
    font-size: 35px;
    background: linear-gradient(135deg, #00e5ff, #8a2be2);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.cert-content h3 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 12px;
    color: #fff;
}

.cert-issuer {
    font-size: 13px;
    color: rgba(255,255,255,0.6);
    margin-bottom: 8px;
}

.cert-issuer i {
    color: #00e5ff;
    margin-right: 6px;
}

.cert-date {
    font-size: 12px;
    color: rgba(255,255,255,0.5);
}

.cert-date i {
    color: #00e5ff;
    margin-right: 6px;
}

@media (max-width: 768px) {
    .cert-stats {
        gap: 15px;
    }
    .stat-item {
        padding: 15px 20px;
        min-width: 100px;
    }
    .stat-number {
        font-size: 22px;
    }
    .certifications-grid {
        grid-template-columns: 1fr;
    }
    .cert-filters .filter-btn {
        padding: 6px 16px;
        font-size: 12px;
    }
}

    /* Projects Section */
    .projects {
        background: transparent;
    }

    .projects-filter {
        display: flex;
        justify-content: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .filter-btn {
        padding: 8px 20px;
        background: rgba(255, 255, 255, 0.1);
        border: none;
        color: #fff;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .filter-btn.active,
    .filter-btn:hover {
        background: #00e5ff;
        color: #010714;
    }

    .projects-content {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 30px;
    }

    .project-item {
        position: relative;
        border-radius: 10px;
        overflow: hidden;
    }

    .project-item img {
        width: 100%;
        height: 250px;
        object-fit: cover;
        transition: all 0.5s;
    }

    .project-info {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 229, 255, 0.9);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        opacity: 0;
        transition: all 0.5s;
    }

    .project-item:hover .project-info {
        opacity: 1;
    }

    .project-item:hover img {
        transform: scale(1.1);
    }

    .project-info h3 {
        color: #010714;
        margin-bottom: 5px;
    }

    .project-info p {
        color: #010714;
        margin-bottom: 15px;
    }

    .project-link {
        display: inline-flex;
        justify-content: center;
        align-items: center;
        width: 40px;
        height: 40px;
        background: #fff;
        border-radius: 50%;
        color: #00e5ff;
        text-decoration: none;
        transition: all 0.3s;
    }

    .project-link:hover {
        background: #010714;
        color: #fff;
    }

/* Testimonials Section */
.testimonials {
    padding: 80px 0;
    background: transparent;
}

.testimonials .section-title p {
    color: rgba(255,255,255,0.6);
    font-size: 14px;
    margin-top: 8px;
}

.testimonials-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 30px;
    margin-top: 40px;
}

.testimonial-card {
    background: rgba(3, 11, 39, 0.6);
    backdrop-filter: blur(12px);
    border-radius: 20px;
    padding: 25px;
    border: 1px solid rgba(0, 229, 255, 0.15);
    transition: all 0.3s;
}

.testimonial-card:hover {
    transform: translateY(-5px);
    border-color: rgba(0, 229, 255, 0.4);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.testimonial-rating {
    margin-bottom: 15px;
    color: #f59e0b;
}

.testimonial-rating i {
    margin-right: 3px;
    font-size: 14px;
}

.testimonial-text {
    position: relative;
    margin-bottom: 20px;
}

.testimonial-text i:first-child {
    position: absolute;
    top: -10px;
    left: -5px;
    font-size: 20px;
    color: rgba(0, 229, 255, 0.3);
}

.testimonial-text i:last-child {
    position: absolute;
    bottom: -10px;
    right: -5px;
    font-size: 20px;
    color: rgba(0, 229, 255, 0.3);
}

.testimonial-text p {
    padding: 0 20px;
    font-style: italic;
    line-height: 1.6;
    color: rgba(255,255,255,0.85);
    font-size: 14px;
}

.testimonial-author {
    border-top: 1px solid rgba(0, 229, 255, 0.1);
    padding-top: 15px;
    margin-top: 10px;
}

.testimonial-author h4 {
    color: #00e5ff;
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 5px;
}

.testimonial-author p {
    color: rgba(255,255,255,0.5);
    font-size: 12px;
}

.no-certs, .no-testimonials {
    text-align: center;
    padding: 50px;
    background: rgba(3, 11, 39, 0.6);
    border-radius: 20px;
    grid-column: 1 / -1;
}

.no-certs i, .no-testimonials i {
    font-size: 48px;
    color: #00e5ff;
    margin-bottom: 15px;
    opacity: 0.5;
}

@media (max-width: 768px) {
    .cert-card {
        flex-direction: column;
        text-align: center;
    }
    .testimonials-grid {
        grid-template-columns: 1fr;
    }
    .certifications-grid {
        grid-template-columns: 1fr;
    }
}

    /* Contact Section */
    .contact {
        background: transparent;
    }

    .contact-content {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 30px;
    }

    .contact-info {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .contact-item {
        background: rgba(3, 11, 39, 0.8);
        backdrop-filter: blur(10px);
        padding: 20px;
        border-radius: 10px;
        transition: all 0.3s;
        border: 1px solid rgba(0, 229, 255, 0.1);
    }

    .contact-item:hover {
        transform: translateY(-5px);
        border-color: #00e5ff;
    }

    .contact-item i {
        font-size: 24px;
        color: #00e5ff;
        margin-bottom: 15px;
    }

    .contact-item h3 {
        margin-bottom: 5px;
    }

    .contact-item a {
        color: #fff;
        text-decoration: none;
    }

    .contact-item a:hover {
        color: #00e5ff;
    }

    .contact-form {
        background: rgba(3, 11, 39, 0.8);
        backdrop-filter: blur(10px);
        padding: 30px;
        border-radius: 10px;
        border: 1px solid rgba(0, 229, 255, 0.1);
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 15px;
        background: rgba(255, 255, 255, 0.1);
        border: none;
        border-radius: 5px;
        color: #fff;
        font-size: 16px;
    }

    .form-group textarea {
        height: 150px;
        resize: none;
    }

    .form-group input:focus,
    .form-group textarea:focus {
        background: rgba(255, 255, 255, 0.2);
        outline: none;
    }

    .submit-btn {
        padding: 12px 30px;
        background: linear-gradient(45deg, #00e5ff, #8a2be2);
        color: #fff;
        border: none;
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .submit-btn:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 229, 255, 0.4);
    }

    /* Footer */
    footer {
        background: rgba(1, 7, 20, 0.9);
        padding: 30px 0;
        text-align: center;
    }

    .footer-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }

    .footer-social {
        display: flex;
        gap: 10px;
    }

    .footer-social a {
        display: inline-flex;
        justify-content: center;
        align-items: center;
        width: 40px;
        height: 40px;
        background: transparent;
        border: 2px solid #00e5ff;
        border-radius: 50%;
        color: #00e5ff;
        transition: all 0.3s;
        text-decoration: none;
    }

    .footer-social a:hover {
        background: #00e5ff;
        color: #010714;
        transform: translateY(-3px);
    }

    /* Scroll Top */
    .scroll-top {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 40px;
        height: 40px;
        background: linear-gradient(45deg, #00e5ff, #8a2be2);
        display: flex;
        justify-content: center;
        align-items: center;
        border-radius: 50%;
        color: #fff;
        opacity: 0;
        pointer-events: none;
        transition: all 0.3s;
        text-decoration: none;
    }

    .scroll-top.active {
        opacity: 1;
        pointer-events: auto;
    }

/* Animation Keyframes */
@keyframes floatBadge {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.05); opacity: 0.8; }
    100% { transform: scale(1); opacity: 1; }
}

@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Smooth transitions for all elements */
.service-card, .cert-card, .testimonial-card, .project-item, .stat-card, .quick-stat-card {
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

/* Hover glow effect */
.service-card:hover, .cert-card:hover, .testimonial-card:hover, .project-item:hover {
    box-shadow: 0 15px 35px rgba(0, 229, 255, 0.2);
}

/* Button click effect */
.more-btn:active, .download-cv:active, .submit-btn:active {
    transform: scale(0.95);
}

/* Image container animation */
.image-container {
    animation: float 4s ease-in-out infinite;
}

/* Section fade in */
section {
    animation: fadeInUp 0.8s ease-out;
}

/* Progress bar animation */
.progress {
    transition: width 1.2s cubic-bezier(0.22, 0.97, 0.36, 1.02);
}

    /* Responsive for badges - UPDATED */
    @media (max-width: 991px) {
        .hero-content {
            flex-direction: column;
            text-align: center;
        }
        .hero-text {
            padding-right: 0;
            margin-bottom: 50px;
        }
        .social-icons {
            justify-content: center;
        }
        .about-content {
            text-align: center;
       
        .personal-info {
            text-align: left;
        }
        .contact-content {
            grid-template-columns: 1fr;
        }
        .menu-toggle {
            display: block;
        }
        nav {
            position: fixed;
            top: 80px;
            left: -100%;
            width: 100%;
            background: rgba(1, 7, 20, 0.95);
            padding: 20px;
            transition: all 0.5s;
        }
        nav.active {
            left: 0;
        }
        nav ul {
            flex-direction: column;
            align-items: center;
        }
        nav ul li {
            margin: 15px 0;
        }

        /* New responsive for badges */
        .floating-badge {
            display: none;
        }

        .hero-text h1 {
            font-size: 42px;
        }

        .hero-text h3 {
            font-size: 24px;
        }
    }

    @media (max-width: 768px) {
        .hero-text h1 {
            font-size: 36px;
        }
        .image-container {
            width: 280px;
            height: 280px;
        }
        .personal-info {
            grid-template-columns: 1fr;
        }
        .footer-content {
            flex-direction: column;
            text-align: center;
        }

        /* New responsive for badges */
        .greeting-badge {
            font-size: 11px;
            padding: 6px 14px;
        }

        .hero-text h1 {
            font-size: 32px;
        }

        .hero-text h3 {
            font-size: 20px;
        }
    }
</style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="logo">
                <h1><?php echo htmlspecialchars($info['full_name']); ?></h1>
            </div>
            <nav>
                <ul>
                    <li><a href="#home" class="active">Koti</a></li>
                    <li><a href="#about">Tietoa minusta</a></li>
                    <li><a href="#education">Koulutus</a></li>
                    <li><a href="#experience">Työkokemus</a></li>
                    <li><a href="#skills">Taidot</a></li>
                    <li><a href="#certifications">Sertifikaatit</a></li>
                    <li><a href="#projects">Projektit</a></li>
                    <li><a href="#testimonials">Palautteet</a></li>
                    <li><a href="blog.php">Blogi</a></li>
                    <li><a href="#contact">Ota yhteyttä</a></li>
                </ul>
            </nav>
            <div class="menu-toggle">
                <i class="fas fa-bars"></i>
            </div>
        </div>
    </header>

<!-- Hero Section -->
<section id="home" class="hero">
    <div class="container">
        <div class="hero-content">
            <div class="hero-text">

                <h3>Ja olen <span class="typing-text"><?php echo htmlspecialchars($info['title']); ?></span></h3>
                <p>
                    <strong>💻 Verkkokehittäjä | 🔧 Tekniikan moniosaaja | 🎯 Ongelmanratkaisija</strong><br><br>
                    <?php echo htmlspecialchars($info['bio']); ?>
                </p>
                <div class="social-icons">
                    <?php
                    $social_result = $conn->query("SELECT * FROM social_links ORDER BY display_order");
                    while($social = $social_result->fetch_assoc()):
                    ?>
                        <a href="<?php echo htmlspecialchars($social['url']); ?>" class="social-icon" target="_blank" title="<?php echo htmlspecialchars($social['platform']); ?>">
                            <?php if($social['platform'] == 'Teams'): ?>
                                <i class="fab fa-microsoft"></i>
                            <?php elseif($social['platform'] == 'WhatsApp'): ?>
                                <i class="fab fa-whatsapp"></i>
                            <?php elseif($social['platform'] == 'GitHub'): ?>
                                <i class="fab fa-github"></i>
                            <?php elseif($social['platform'] == 'LinkedIn'): ?>
                                <i class="fab fa-linkedin-in"></i>
                            <?php elseif($social['platform'] == 'Facebook'): ?>
                                <i class="fab fa-facebook-f"></i>
                            <?php elseif($social['platform'] == 'Instagram'): ?>
                                <i class="fab fa-instagram"></i>
                            <?php elseif($social['platform'] == 'Twitter'): ?>
                                <i class="fab fa-twitter"></i>
                            <?php else: ?>
                                <i class="<?php echo htmlspecialchars($social['icon_class']); ?>"></i>
                            <?php endif; ?>
                        </a>
                    <?php endwhile; ?>
                </div>
                <a href="#about" class="more-btn">
                    <span>Lisätietoa minusta</span>
                    <i class="fas fa-arrow-down"></i>
                </a>
            </div>
            <div class="hero-image">
                <div class="image-container">
                    <img src="<?php echo htmlspecialchars($info['profile_image']); ?>" alt="<?php echo htmlspecialchars($info['full_name']); ?>">
                </div>
                <div class="floating-badge experience-badge">
                    <i class="fas fa-code"></i>
                    <span>3+ Years Experience</span>
                </div>
                <div class="floating-badge projects-badge">
                    <i class="fas fa-project-diagram"></i>
                    <span>5+ Projects</span>
                </div>
            </div>
        </div>
    </div>
    <a href="#" class="scroll-top">
        <i class="fas fa-arrow-up"></i>
    </a>
</section>

<!-- About Section - Modern Short Version -->
<section id="about" class="about">
    <div class="container">
        <div class="section-title">
            <h2>Tietoa minusta</h2>
        </div>
        <div class="about-content">
            <div class="about-image">
                <img src="<?php echo htmlspecialchars($info['about_image']); ?>" alt="<?php echo htmlspecialchars($info['full_name']); ?>">
            </div>
            <div class="about-text">
                <h3>💡 Tekniikan moniosaaja | 🚀 Verkkokehittäjä | 🔧 Ongelmanratkaisija</h3>

                <p>Olen <strong><?php echo htmlspecialchars($info['full_name']); ?></strong>, 
                <strong>verkkokehittäjä</strong>, joka yhdistää ohjelmointiosaamisen vahvaan 
                <strong>sähkö- ja rakennusalan taustaan</strong>. Tämä ainutlaatuinen yhdistelmä tekee minusta 
                loogisen ajattelijan, joka ymmärtää teknologian jokaista tasoa.</p>

                <p>🎯 <strong>Vahvuuteni:</strong> Modernit verkkosivut, responsiivinen suunnittelu, 
                tietokantaratkaisut, käyttäjäystävälliset käyttöliittymät ja projektinhallinta.</p>

                <p>🌟 <strong>Arvoni:</strong> Laatu, tarkkuus, luotettavuus ja jatkuva oppiminen.</p>

                <p>📈 <strong>Tavoitteeni:</strong> Luoda innovatiivisia verkkoratkaisuja, 
                jotka tekevät käyttäjien arjesta helpompaa ja tehokkaampaa.</p>

                <div class="personal-info">
                    <div class="info-item">
                        <span>📅 Syntymäpäivä:</span> <?php echo date('d.m.Y', strtotime($info['birth_date'])); ?>
                    </div>
                    <div class="info-item">
                        <span>📞 Puhelin:</span> <a href="tel:<?php echo $info['phone']; ?>"><?php echo $info['phone']; ?></a>
                    </div>
                    <div class="info-item">
                        <span>✉️ Sähköposti:</span> <a href="mailto:<?php echo $info['email']; ?>"><?php echo $info['email']; ?></a>
                    </div>
                    <div class="info-item">
                        <span>📍 Kaupunki:</span> <?php echo $info['city']; ?>
                    </div>
                </div>

                <a href="<?php echo $cv_path; ?>" class="download-cv" download>
                    <i class="fas fa-download"></i> Lataa CV
                </a>
            </div>
        </div>
    </div>
</section>

    <!-- Education Section -->
    <section id="education" class="services">
        <div class="container">
            <div class="section-title">
                <h2>Koulutus</h2>
            </div>
            <div class="services-content">
                <?php 
                $edu_result = $conn->query("SELECT * FROM education ORDER BY display_order");
                while($edu = $edu_result->fetch_assoc()): 
                ?>
                <div class="service-card">
                    <i class="fas <?php echo $edu['icon']; ?>"></i>
                    <h3><?php echo htmlspecialchars($edu['title']); ?></h3>
                    <p><?php echo htmlspecialchars($edu['institution']); ?></p>
                    <small><?php echo htmlspecialchars($edu['period']); ?></small>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <!-- Experience Section -->
    <section id="experience" class="services">
        <div class="container">
            <div class="section-title">
                <h2>Työkokemus</h2>
            </div>
            <div class="services-content">
                <?php 
                $exp_result = $conn->query("SELECT * FROM experience ORDER BY display_order");
                while($exp = $exp_result->fetch_assoc()): 
                ?>
                <div class="service-card">
                    <i class="fas <?php echo $exp['icon']; ?>"></i>
                    <h3><?php echo htmlspecialchars($exp['title']); ?></h3>
                    <p><?php echo htmlspecialchars($exp['company']); ?></p>
                    <small><?php echo htmlspecialchars($exp['period']); ?></small>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <!-- Skills Section -->
    <section id="skills" class="skills">
        <div class="container">
            <div class="section-title">
                <h2>Kielet & Taidot</h2>
            </div>
            <div class="skills-content">
                <?php 
                $skills_result = $conn->query("SELECT * FROM skills ORDER BY display_order");
                while($skill = $skills_result->fetch_assoc()): 
                ?>
                <div class="skill-item">
                    <h4><?php echo htmlspecialchars($skill['skill_name']); ?> <span><?php echo $skill['percentage']; ?>%</span></h4>
                    <div class="progress-bar">
                        <div class="progress" style="width: <?php echo $skill['percentage']; ?>%;"></div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

<!-- Certifications Section -->
<section id="certifications" class="certifications">
    <div class="container">
        <div class="section-title">
            <h2>Sertifikaatit</h2>
            <p>Omat todistukset ja suoritetut kurssit</p>
        </div>

        <!-- Stats Cards -->
        <div class="cert-stats">
            <div class="stat-item">
                <i class="fas fa-certificate"></i>
                <span class="stat-number"><?php echo $conn->query("SELECT COUNT(*) as count FROM certifications")->fetch_assoc()['count']; ?></span>
                <span class="stat-label">Sertifikaattia</span>
            </div>
            <div class="stat-item">
                <i class="fas fa-calendar-alt"></i>
                <span class="stat-number">2023-2025</span>
                <span class="stat-label">Aktiivinen</span>
            </div>
            <div class="stat-item">
                <i class="fas fa-globe"></i>
                <span class="stat-number">6+</span>
                <span class="stat-label">Oppimisalustaa</span>
            </div>
        </div>

        <!-- Filter Buttons -->
        <div class="cert-filters">
            <button class="filter-btn active" data-filter="all">Kaikki</button>
            <button class="filter-btn" data-filter="web">Web-kehitys</button>
            <button class="filter-btn" data-filter="database">Tietokannat</button>
            <button class="filter-btn" data-filter="programming">Ohjelmointi</button>
        </div>

        <div class="certifications-grid" id="certGrid">
            <?php
            $certs_sql = "SELECT * FROM certifications ORDER BY display_order, issue_date DESC";
            $certs_result = $conn->query($certs_sql);
            
            if ($certs_result && $certs_result->num_rows > 0):
                while($cert = $certs_result->fetch_assoc()):
                    // Determine category based on title
                    $category = 'web';
                    if (strpos($cert['title'], 'PHP') !== false || strpos($cert['title'], 'SQL') !== false || strpos($cert['title'], 'Database') !== false) {
                        $category = 'database';
                    } elseif (strpos($cert['title'], 'Python') !== false || strpos($cert['title'], 'JavaScript') !== false) {
                        $category = 'programming';
                    }
            ?>
            <div class="cert-card" data-category="<?php echo $category; ?>">
                <div class="cert-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <div class="cert-content">
                    <h3><?php echo htmlspecialchars($cert['title']); ?></h3>
                    <p class="cert-issuer"><i class="fas fa-building"></i> <?php echo htmlspecialchars($cert['issuer']); ?></p>
                    <p class="cert-date"><i class="far fa-calendar-alt"></i> <?php echo date('d.m.Y', strtotime($cert['issue_date'])); ?></p>
                </div>
            </div>
            <?php 
                endwhile;
            else:
            ?>
            <div class="no-certs">
                <i class="fas fa-certificate"></i>
                <p>Sertifikaatteja tulossa pian!</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

    <!-- Projects Section -->
    <section id="projects" class="projects">
        <div class="container">
            <div class="section-title">
                <h2>Projektini</h2>
            </div>
            <div class="projects-filter">
                <button class="filter-btn active" data-filter="all">Kaikki</button>
                <button class="filter-btn" data-filter="web">Verkkosuunnittelu</button>
                <button class="filter-btn" data-filter="python">Python</button>
            </div>
            <div class="projects-content">
                <?php 
                $projects_result = $conn->query("SELECT * FROM projects ORDER BY display_order");
                while($project = $projects_result->fetch_assoc()): 
                ?>
                <div class="project-item" data-category="<?php echo $project['category']; ?>">
                    <img src="<?php echo htmlspecialchars($project['image_url']); ?>" alt="<?php echo $project['title']; ?>">
                    <div class="project-info">
                        <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                        <p><?php echo ucfirst($project['category']); ?></p>
                        <a href="<?php echo htmlspecialchars($project['project_url']); ?>" class="project-link" target="_blank"><i class="fas fa-link"></i></a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

<!-- Testimonials Section -->
<section id="testimonials" class="testimonials">
    <div class="container">
        <div class="section-title">
            <h2>Asiakaspalautteet</h2>
            <p>Mitä asiakkaani sanovat työstäni</p>
        </div>
        <div class="testimonials-grid">
            <?php
            $testimonial_sql = "SELECT * FROM testimonials WHERE is_visible = 1 ORDER BY display_order, id DESC LIMIT 6";
            $testimonial_result = $conn->query($testimonial_sql);
            
            if ($testimonial_result && $testimonial_result->num_rows > 0):
                while($testimonial = $testimonial_result->fetch_assoc()):
            ?>
            <div class="testimonial-card">
                <div class="testimonial-rating">
                    <?php for($i=1; $i<=5; $i++): ?>
                        <i class="fas fa-star<?php echo $i <= $testimonial['rating'] ? '' : '-o'; ?>"></i>
                    <?php endfor; ?>
                </div>
                <div class="testimonial-text">
                    <i class="fas fa-quote-left"></i>
                    <p><?php echo htmlspecialchars($testimonial['testimonial']); ?></p>
                    <i class="fas fa-quote-right"></i>
                </div>
                <div class="testimonial-author">
                    <h4><?php echo htmlspecialchars($testimonial['client_name']); ?></h4>
                    <p><?php echo htmlspecialchars($testimonial['client_position']); ?></p>
                </div>
            </div>
            <?php 
                endwhile;
            else:
            ?>
            <div class="no-testimonials">
                <i class="fas fa-comments"></i>
                <p>Asiakaspalautteita tulossa pian!</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

    <!-- Contact Section -->
    <section id="contact" class="contact">
        <div class="container">
            <div class="section-title">
                <h2>Ota yhteyttä</h2>
            </div>
            <div class="contact-content">
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <h3>Osoite</h3>
                        <p><?php echo $info['address']; ?></p>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <h3>Sähköposti</h3>
                        <p><a href="mailto:<?php echo $info['email']; ?>"><?php echo $info['email']; ?></a></p>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <h3>Puhelin</h3>
                        <p><a href="tel:<?php echo $info['phone']; ?>"><?php echo $info['phone']; ?></a></p>
                    </div>
                </div>
                <div class="contact-form">
                    <form id="contactForm" method="POST" action="contact_process.php">
                        <div class="form-group">
                            <input type="text" name="name" placeholder="Nimesi" required>
                        </div>
                        <div class="form-group">
                            <input type="email" name="email" placeholder="Sähköpostisi" required>
                        </div>
                        <div class="form-group">
                            <input type="text" name="subject" placeholder="Aihe" required>
                        </div>
                        <div class="form-group">
                            <textarea name="message" placeholder="Viestisi" required></textarea>
                        </div>
                        <button type="submit" class="submit-btn">Lähetä viesti</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

<!-- Footer -->
<footer>
    <div class="container">
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> <?php echo $info['full_name']; ?>. Kaikki oikeudet pidätetään.</p>
            <div class="footer-social">
                <?php
                $social_result = $conn->query("SELECT * FROM social_links ORDER BY display_order");
                while($social = $social_result->fetch_assoc()):
                ?>
                    <a href="<?php echo htmlspecialchars($social['url']); ?>" class="footer-social-icon" target="_blank" title="<?php echo htmlspecialchars($social['platform']); ?>">
                        <?php if($social['platform'] == 'Teams'): ?>
                            <i class="fab fa-microsoft"></i>
                        <?php elseif($social['platform'] == 'WhatsApp'): ?>
                            <i class="fab fa-whatsapp"></i>
                        <?php elseif($social['platform'] == 'GitHub'): ?>
                            <i class="fab fa-github"></i>
                        <?php elseif($social['platform'] == 'LinkedIn'): ?>
                            <i class="fab fa-linkedin-in"></i>
                        <?php elseif($social['platform'] == 'Facebook'): ?>
                            <i class="fab fa-facebook-f"></i>
                        <?php elseif($social['platform'] == 'Instagram'): ?>
                            <i class="fab fa-instagram"></i>
                        <?php elseif($social['platform'] == 'Twitter'): ?>
                            <i class="fab fa-twitter"></i>
                        <?php else: ?>
                            <i class="<?php echo htmlspecialchars($social['icon_class']); ?>"></i>
                        <?php endif; ?>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</footer>
<script>
    $(document).ready(function() {
        // Typing animation
        var typed = new Typed('.typing-text', {
            strings: ['<?php echo $info['title']; ?>', 'Web Developer', 'Tech Enthusiast', 'Problem Solver'],
            typeSpeed: 100,
            backSpeed: 60,
            loop: true
        });

        // Sticky Header
        $(window).on('scroll', function() {
            if ($(this).scrollTop() > 100) {
                $('header').addClass('sticky');
                $('.scroll-top').addClass('active');
            } else {
                $('header').removeClass('sticky');
                $('.scroll-top').removeClass('active');
            }
        });

        // Smooth scroll
        $('a[href^="#"]').not('.scroll-top').on('click', function(e) {
            e.preventDefault();
            var target = $(this.hash);
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 80
                }, 800);
            }
        });

        // Scroll to top
        $('.scroll-top').on('click', function(e) {
            e.preventDefault();
            $('html, body').animate({scrollTop: 0}, 800);
        });

        // Mobile menu toggle
        $('.menu-toggle').click(function() {
            $('nav').toggleClass('active');
            $(this).find('i').toggleClass('fa-bars fa-times');
        });

        // Close menu on link click
        $('nav ul li a').click(function() {
            $('nav').removeClass('active');
            $('.menu-toggle i').removeClass('fa-times').addClass('fa-bars');
        });

        // Project filter
        $('.filter-btn').click(function() {
            const value = $(this).attr('data-filter');
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            if (value === 'all') {
                $('.project-item').show();
            } else {
                $('.project-item').hide();
                $('.project-item[data-category="' + value + '"]').show();
            }
        });

        // Certifications Filter
        $('.cert-filters .filter-btn').click(function() {
            var filterValue = $(this).attr('data-filter');
            $('.cert-filters .filter-btn').removeClass('active');
            $(this).addClass('active');
            if (filterValue === 'all') {
                $('.cert-card').show();
            } else {
                $('.cert-card').hide();
                $('.cert-card[data-category="' + filterValue + '"]').show();
            }
        });

        // ========================================
        // NEW ANIMATIONS
        // ========================================

        // 1. Fade In Animation for all sections
        $('section').css('opacity', '0');
        
        function fadeInSections() {
            $('section').each(function(i) {
                var section = $(this);
                var sectionTop = section.offset().top;
                var windowBottom = $(window).scrollTop() + $(window).height();
                
                if (windowBottom > sectionTop + 100) {
                    section.delay(i * 100).animate({opacity: 1}, 800);
                }
            });
        }
        
        fadeInSections();
        $(window).on('scroll', fadeInSections);

        // 2. Animate stat cards on scroll
        $('.stat-item, .stat-card, .quick-stat-card').css('opacity', '0').css('transform', 'translateY(30px)');
        
        function animateStats() {
            $('.stat-item, .stat-card, .quick-stat-card').each(function(i) {
                var element = $(this);
                var elementTop = element.offset().top;
                var windowBottom = $(window).scrollTop() + $(window).height();
                
                if (windowBottom > elementTop + 50) {
                    element.delay(i * 100).animate({
                        opacity: 1,
                        transform: 'translateY(0)'
                    }, 600);
                }
            });
        }
        
        animateStats();
        $(window).on('scroll', animateStats);

        // 3. Animate skill bars with delay
        $('.progress').each(function() {
            var width = $(this).css('width');
            $(this).css('width', '0');
            $(this).data('width', width);
        });
        
        function animateSkills() {
            $('.skill-item').each(function(i) {
                var skill = $(this);
                var skillTop = skill.offset().top;
                var windowBottom = $(window).scrollTop() + $(window).height();
                
                if (windowBottom > skillTop + 50) {
                    var progress = skill.find('.progress');
                    var targetWidth = progress.data('width');
                    progress.delay(i * 150).animate({width: targetWidth}, 1000);
                }
            });
        }
        
        animateSkills();
        $(window).on('scroll', animateSkills);

        // 4. Animate service cards on hover (glow effect)
        $('.service-card, .cert-card, .testimonial-card, .project-item').hover(
            function() {
                $(this).css({
                    'transform': 'translateY(-10px) scale(1.02)',
                    'transition': 'all 0.3s ease'
                });
            },
            function() {
                $(this).css({
                    'transform': 'translateY(0) scale(1)'
                });
            }
        );

        // 5. Animate floating badges
        $('.floating-badge').each(function(i) {
            $(this).css('animation', 'floatBadge 3s ease-in-out infinite');
            $(this).css('animation-delay', i * 0.5 + 's');
        });

        // 6. Add pulse animation to buttons on click
        $('.more-btn, .download-cv, .submit-btn, .filter-btn, .cert-filters .filter-btn').click(function() {
            $(this).css('transform', 'scale(0.95)');
            setTimeout(() => {
                $(this).css('transform', 'scale(1)');
            }, 150);
        });

        // 7. Animate social icons on hover
        $('.social-icon, .footer-social a').hover(
            function() {
                $(this).css({
                    'transform': 'rotate(360deg) scale(1.2)',
                    'transition': 'all 0.5s ease'
                });
            },
            function() {
                $(this).css({
                    'transform': 'rotate(0deg) scale(1)'
                });
            }
        );

        // 8. Add shimmer effect to cards on mouse move
        $('.service-card, .cert-card, .testimonial-card').mousemove(function(e) {
            var card = $(this);
            var x = e.pageX - card.offset().left;
            var y = e.pageY - card.offset().top;
            var centerX = card.width() / 2;
            var centerY = card.height() / 2;
            var rotateX = (y - centerY) / 20;
            var rotateY = (centerX - x) / 20;
            
            card.css({
                'transform': 'perspective(1000px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg) translateY(-5px)',
                'transition': 'transform 0.1s ease'
            });
        }).mouseleave(function() {
            $(this).css({
                'transform': 'perspective(1000px) rotateX(0) rotateY(0) translateY(0)',
                'transition': 'transform 0.3s ease'
            });
        });

        // 9. Animate counter numbers
        $('.stat-number').each(function() {
            var $this = $(this);
            var countTo = parseInt($this.text().replace(/[^0-9]/g, ''));
            
            if (!isNaN(countTo)) {
                var countFrom = 0;
                $({countNum: countFrom}).animate({
                    countNum: countTo
                }, {
                    duration: 2000,
                    easing: 'swing',
                    step: function() {
                        $this.text(Math.floor(this.countNum));
                    },
                    complete: function() {
                        $this.text(countTo);
                    }
                });
            }
        });

        // 10. Add parallax effect to background
        $(window).on('scroll', function() {
            var scrollPos = $(this).scrollTop();
            $('body::before').css('transform', 'translateY(' + scrollPos * 0.3 + 'px)');
        });

        // 11. Animate hero text on load
        $('.hero-text h1, .hero-text h3, .hero-text p, .hero-text .more-btn, .hero-text .social-icons').css('opacity', '0').css('transform', 'translateY(30px)');
        
        setTimeout(function() {
            $('.hero-text h1').animate({opacity: 1, transform: 'translateY(0)'}, 500);
            $('.hero-text h3').delay(200).animate({opacity: 1, transform: 'translateY(0)'}, 500);
            $('.hero-text p').delay(400).animate({opacity: 1, transform: 'translateY(0)'}, 500);
            $('.hero-text .social-icons').delay(600).animate({opacity: 1, transform: 'translateY(0)'}, 500);
            $('.hero-text .more-btn').delay(800).animate({opacity: 1, transform: 'translateY(0)'}, 500);
        }, 100);
    });
</script>
</body>
</html>
