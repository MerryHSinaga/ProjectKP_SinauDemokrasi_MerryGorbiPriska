<style>
.footer-kpu{
  background:
    linear-gradient(rgba(0,0,0,.75), rgba(0,0,0,.75)),
    url("assets/footer-bg.png") center/cover no-repeat;
  color:#fff;
  padding-bottom:10px;
}

.footer-inner{
  padding:2.2rem 1rem;
}

.footer-title{
  font-size:.9rem;
  font-weight:600;
  margin-bottom:.75rem;
  text-align:left;
}

.footer-kpu ul{
  padding-left:0;
  margin:0;
  list-style:none;
  text-align:left;
}

.footer-link{
  font-size:.85rem;
  color:#858585;
  text-decoration:none;
}

.footer-link:hover{
  transition:0.3s;
  color:#f4c430;
}

/* === DEVELOPER TEAM === */
.footer-developer{
  margin-top:-10px;     
  margin-bottom:20px;      
  font-size:.85rem;
  color:#858585;
  line-height:1.3;      
  text-align:center;
}

/* === LINK DEVELOPER TEAM === */
.footer-developer a{
  color:#858585;             
  text-decoration:none;
  transition:0.3s;
}

.footer-developer a:hover{
  color:#f4c430;             
}

.footer-developer strong{
  display:block;
  margin-bottom:2px;
  font-weight:600;
}

.footer-social{
  display:flex;
  gap:.5rem;
  margin-top:.9rem;
}

.social-btn{
  width:34px;
  height:34px;
  border:1px solid #fff;
  border-radius:6px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  color:#fff;
  text-decoration:none;
  transition:color .3s ease,border-color .3s ease;
}

.social-btn:hover{
  color:#f4c430;
  border-color:#f4c430;
}

.footer-bottom{
  border-top:1px solid rgba(176,176,176,.25);
  min-height:60px;     
  display:flex;
  align-items:center;
  justify-content:center;
  text-align:center;
  font-size:1rem;
  padding:0 1rem;
}

</style>

<footer class="footer-kpu">
  <div class="container footer-inner">
    <div class="row gy-4">

      <!-- KOLOM KIRI -->
      <div class="col-md-5">
        <div class="footer-title fw-light">
          Komisi Pemilihan Umum Daerah Istimewa Yogyakarta
        </div>

        <div class="footer-social">
          <a href="https://x.com/kpudiy" class="social-btn"><i class="bi bi-twitter-x"></i></a>
          <a href="https://www.facebook.com/kpudiy" class="social-btn"><i class="bi bi-facebook"></i></a>
          <a href="https://www.instagram.com/kpudiy/" class="social-btn"><i class="bi bi-instagram"></i></a>
          <a href="https://www.youtube.com/channel/UCG9WJ58787EYKzcbDb23dfA" class="social-btn"><i class="bi bi-youtube"></i></a>
        </div>
      </div>

      <!-- NAVIGASI -->
      <div class="col-md-3">
        <div class="footer-title">Navigasi</div>
        <ul>
          <li><a href="dashboard.php" class="footer-link">Beranda</a></li>
          <li><a href="daftar_materi.php" class="footer-link">Materi</a></li>
          <li><a href="daftar_kuis.php" class="footer-link">Kuis</a></li>
          <li><a href="kontak.php" class="footer-link">Kontak</a></li>
        </ul>
      </div>

      <!-- KONTAK -->
      <div class="col-md-4 footer-contact text-start">
        <div class="footer-title">Kontak Kami</div>
        <p class="mb-1"><a href="kontak.php" class="footer-link">Alamat Kantor</a></p>
        <p class="mb-1"><a href="kontak.php" class="footer-link">Telepon Kantor</a></p>
        <p class="mb-0">
          <a href="https://www.google.com/maps/place/Komisi+Pemilihan+Umum+(+KPU+)+Daerah+Istimewa+Yogyakarta/"
             class="footer-link">
            Maps
          </a>
        </p>
      </div>

    </div>
  </div>

  <div class="footer-bottom">
    © <?php echo date('Y'); ?> Komisi Pemilihan Umum
  </div>

        <!-- DEVELOPER TEAM -->
        <div class="footer-developer">
        <strong>Developer Team:</strong>
        <a href="https://www.linkedin.com/in/merry-helty-sinaga-3a27ab245" target="_blank">
            Merry Helty Sinaga
        </a> |
        <a href="https://www.linkedin.com/in/priska-natalia-sembiring-419a922a1/" target="_blank">
            Priska Natalia Sembiring
        </a> |
        <a href="https://www.linkedin.com/in/gorbi-ello-pasaribu-446b0a297/" target="_blank">
            Gorbi Ello Pasaribu
        </a><br>
        Jurusan Informatika UPN "Veteran" Yogyakarta
        </div>


</footer>
