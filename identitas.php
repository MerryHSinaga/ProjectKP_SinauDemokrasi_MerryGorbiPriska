<?php
$site_title = $site_title ?? 'SI-NAU Demokrasi | KPU DIY';
$site_desc  = $site_desc  ?? 'Media edukasi demokrasi dan kepemiluan berbasis website oleh KPU Daerah Istimewa Yogyakarta.';
$site_image = $site_image ?? 'Asset/LogoWEB.png';
$theme_color = '#700D09';
?>

<title><?= htmlspecialchars($site_title); ?></title>

<link rel="icon" type="image/png" href="Asset/LogoWEB.png">
<link rel="apple-touch-icon" href="Asset/LogoWEB.png">

<meta name="application-name" content="SI-NAU Demokrasi">
<meta name="theme-color" content="<?= $theme_color ?>">

<meta name="description" content="<?= htmlspecialchars($site_desc); ?>">

<meta property="og:title" content="<?= htmlspecialchars($site_title); ?>">
<meta property="og:description" content="<?= htmlspecialchars($site_desc); ?>">
<meta property="og:image" content="<?= $site_image; ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="id_ID">
