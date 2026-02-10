<?php
$activePage = $activePage ?? '';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
:root{
  --maroon:#700D09;
  --gold:#f4c430;
  --navbar-h:90px;
}

.navbar-kpu{
  position:fixed;
  top:0;left:0;right:0;
  height:var(--navbar-h);
  background:var(--maroon);
  border-bottom:1px solid #000;
  z-index:1000;
}

.navbar-kpu .inner{
  max-width:1330px;
  height:100%;
  margin:auto;
  padding:0 16px;
  display:flex;
  align-items:center;
  justify-content:space-between;
}

.brand{
  display:flex;
  align-items:center;
  gap:8px;
  text-decoration:none;
  flex-shrink:0;
}

.brand img{height:36px}

.brand-text{
  color:#fff;
  line-height:1.05;
  gap:2px;
  flex-direction:column;
  display:flex;
}

.brand-text strong{
  font-size:.95rem;
  font-weight:700;
}

.brand-text span{
  font-size:.85rem;
  font-weight:400;
}

.nav-menu{
  display:flex;
  gap:26px;
  align-items:center;
}

.nav-menu a{
  color:#fff;
  font-weight:600;
  font-size:.85rem;
  letter-spacing:.5px;
  text-decoration:none;
  position:relative;
  white-space:nowrap;
}

.nav-menu a::after{
  content:"";
  position:absolute;
  left:0;bottom:-6px;
  width:0;height:3px;
  background:var(--gold);
  transition:.3s;
}

.nav-menu a:hover::after,
.nav-menu a.active::after{
  width:100%;
}

/*Untuk edit HAMBURGER*/
.hamburger{
  display:none;
  position:relative;
}

.hamburger-btn{
  width:40px;
  height:40px;
  border-radius:10px;
  border:1.5px solid #fff;
  background:transparent;
  color:#fff;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:22px;
  cursor:pointer;
  transition:.25s ease;
  z-index:1002;
}

.hamburger-btn:hover{
  background:#fff;
  color:var(--maroon);
}

.hamburger.open .hamburger-btn{
  background:#fff;
  color:var(--maroon);
}

.hamburger-menu{
  position:absolute;
  top:52px;
  right:0;
  background:#fff;
  border-radius:14px;
  min-width:210px;
  padding:10px 0;
  box-shadow:0 18px 40px rgba(0,0,0,.25);
  opacity:0;
  transform:translateY(8px);
  pointer-events:none;
  transition:.25s ease;
  z-index:1002;
}

.hamburger.open .hamburger-menu{
  opacity:1;
  transform:translateY(0);
  pointer-events:auto;
}

.hamburger-menu a{
  display:block;
  padding:10px 20px;
  color:#222;
  font-weight:600;
  font-size:.85rem;
  text-decoration:none;
}

.hamburger-menu a:hover{
  background:#f2f2f2;
  color:var(--maroon);
}

.hamburger-backdrop{
  position:fixed;
  inset:0;
  background:rgba(0,0,0,.25);
  opacity:0;
  pointer-events:none;
  transition:.25s ease;
  z-index:1001;
}

.hamburger.open ~ .hamburger-backdrop{
  opacity:1;
  pointer-events:auto;
}

@media(max-width:992px){
  .nav-menu{display:none}
  .hamburger{display:block}
}

.main-content{
  margin-top:var(--navbar-h);
}
</style>

<nav class="navbar-kpu">
  <div class="inner">

    <a href="dashboard.php" class="brand">
      <img src="Asset/LogoKPU.png" alt="KPU">
      <div class="brand-text">
        <strong>KPU</strong>
        <span>DIY</span>
      </div>
    </a>

    <div class="nav-menu">
      <a href="dashboard.php" class="<?= $activePage==='dashboard'?'active':'' ?>">BERANDA</a>
      <a href="daftar_materi.php" class="<?= $activePage==='materi'?'active':'' ?>">MATERI</a>
      <a href="daftar_kuis.php" class="<?= $activePage==='kuis'?'active':'' ?>">KUIS</a>
      <a href="kontak.php" class="<?= $activePage==='kontak'?'active':'' ?>">KONTAK</a>
      <a href="login_admin.php" class="<?= $activePage==='login'?'active':'' ?>">LOGIN</a>
    </div>

    <div class="hamburger" id="hamburger">
      <div class="hamburger-btn">
        <i class="bi bi-list"></i>
      </div>
      <div class="hamburger-menu">
        <a href="dashboard.php">BERANDA</a>
        <a href="daftar_materi.php">MATERI</a>
        <a href="daftar_kuis.php">KUIS</a>
        <a href="kontak.php">KONTAK</a>
        <a href="login_admin.php">LOGIN</a>
      </div>
    </div>

  </div>
</nav>

<div class="hamburger-backdrop" id="hamburgerBackdrop"></div>

<script>
const hb=document.getElementById('hamburger');
const bd=document.getElementById('hamburgerBackdrop');

hb.addEventListener('click',()=>hb.classList.toggle('open'));
bd.addEventListener('click',()=>hb.classList.remove('open'));

window.addEventListener('resize',()=>{
  if(innerWidth>992) hb.classList.remove('open');
});
</script>

