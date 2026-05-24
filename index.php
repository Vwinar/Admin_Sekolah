<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Informasi Sekolah Terpadu</title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --text-light: #f7fafc;
            --text-dark: #2d3748;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --card-hover: rgba(255, 255, 255, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
            color: var(--text-light);
            overflow-x: hidden;
            min-height: 100vh;
        }

        /* Animated Background */
        .bg-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .shape {
            position: absolute;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            animation: float 20s infinite alternate;
        }

        .shape-1 {
            width: 500px;
            height: 500px;
            top: -100px;
            left: -100px;
        }

        .shape-2 {
            width: 400px;
            height: 400px;
            bottom: -50px;
            right: -50px;
            animation-delay: -5s;
        }

        .shape-3 {
            width: 300px;
            height: 300px;
            top: 40%;
            left: 40%;
            animation-delay: -10s;
            opacity: 0.2;
        }

        @keyframes float {
            0% {
                transform: translate(0, 0) rotate(0deg);
            }

            100% {
                transform: translate(50px, 50px) rotate(20deg);
            }
        }

        /* Navbar */
        .navbar {
            padding: 1.5rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 100;
            background: rgba(26, 32, 44, 0.5);
            border-bottom: 1px solid var(--glass-border);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(to right, #fff, #a3bffa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 8rem 2rem 4rem;
        }

        .hero h1 {
            font-size: 3.5rem;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            font-weight: 800;
            max-width: 900px;
        }

        .highlight {
            color: #a3bffa;
            position: relative;
            display: inline-block;
        }

        .hero p {
            font-size: 1.25rem;
            color: #cbd5e0;
            margin-bottom: 3rem;
            max-width: 600px;
            line-height: 1.6;
        }

        .cta-button {
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 1rem 2.5rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(118, 75, 162, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .cta-button:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(118, 75, 162, 0.6);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .cta-button i {
            transition: transform 0.3s;
        }

        .cta-button:hover i {
            transform: translateX(5px);
        }

        /* Features Grid */
        .features {
            padding: 4rem 5%;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .feature-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            padding: 2.5rem;
            border-radius: 20px;
            backdrop-filter: blur(20px);
            transition: all 0.4s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            background: var(--card-hover);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .icon-box {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            color: #a3bffa;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: white;
        }

        .feature-card p {
            color: #cbd5e0;
            line-height: 1.6;
            font-size: 0.95rem;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 3rem;
            margin-top: 4rem;
            border-top: 1px solid var(--glass-border);
            color: #718096;
            font-size: 0.9rem;
            backdrop-filter: blur(10px);
            background: rgba(26, 32, 44, 0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .features {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <!-- Background Animation -->
    <div class="bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <!-- Navbar -->
    <nav class="navbar" data-aos="fade-down">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i> Eduvay
        </div>
        <a href="laporan_harian/" class="cta-button" style="padding: 0.7rem 1.5rem; font-size: 0.9rem;">
            Login <i class="fas fa-arrow-right"></i>
        </a>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div data-aos="zoom-in" data-aos-duration="1000">
            <h1>Transformasi Digital <br><span class="highlight">Manajemen Sekolah</span></h1>
            <p>Platform terintegrasi untuk Laporan Harian, Absensi, Literasi, dan Administrasi Kelas. Kelola aktivitas
                sekolah dengan lebih cerdas, cepat, dan efisien.</p>
            <a href="laporan_harian/" class="cta-button">
                Masuk ke Aplikasi <i class="fas fa-rocket"></i>
            </a>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
            <div class="icon-box"><i class="fas fa-clipboard-check"></i></div>
            <h3>Laporan Harian</h3>
            <p>Dokumentasi aktivitas guru secara digital dengan pelaporan yang terstruktur dan mudah diakses kapan saja.
            </p>
        </div>

        <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
            <div class="icon-box"><i class="fas fa-map-marker-alt"></i></div>
            <h3>Absensi Geotagging</h3>
            <p>Sistem presensi modern menggunakan GPS untuk memastikan kedisiplinan dan akurasi lokasi guru serta siswa.
            </p>
        </div>

        <div class="feature-card" data-aos="fade-up" data-aos-delay="300">
            <div class="icon-box"><i class="fas fa-book-reader"></i></div>
            <h3>Literasi & Numerasi</h3>
            <p>Program pengembangan kemampuan literasi dan numerasi siswa yang terintegrasi langsung dalam satu
                platform.</p>
        </div>

        <div class="feature-card" data-aos="fade-up" data-aos-delay="400">
            <div class="icon-box"><i class="fas fa-users-cog"></i></div>
            <h3>Administrasi Kelas</h3>
            <p>Kelola data siswa, jadwal pelajaran, dan perangkat pembelajaran dengan mudah dalam satu dashboard.</p>
        </div>

        <div class="feature-card" data-aos="fade-up" data-aos-delay="500">
            <div class="icon-box"><i class="fas fa-chart-line"></i></div>
            <h3>Monitoring & Analitik</h3>
            <p>Pantau perkembangan kinerja dan statistik sekolah melalui visualisasi data yang informatif dan real-time.
            </p>
        </div>

        <div class="feature-card" data-aos="fade-up" data-aos-delay="600">
            <div class="icon-box"><i class="fas fa-file-invoice"></i></div>
            <h3>Jurnal Mengajar</h3>
            <p>Rekam jejak materi pembelajaran dan catatan harian kelas yang terorganisir rapi untuk evaluasi berkala.
            </p>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2024 Sistem Informasi Sekolah Terpadu. All rights reserved.</p>
    </footer>

    <!-- Scripts -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            once: true,
            offset: 100,
            duration: 800,
            easing: 'ease-out-cubic'
        });
    </script>
</body>

</html>