<?php
$cfg['blowfish_secret'] = 'votre_secret_32_caracteres'; // Générer une clé aléatoire (32 caractères)
$i = 0;
$i++;
$cfg['Servers'][$i]['auth_type'] = 'config';
$cfg['Servers'][$i]['host'] = 'mysql-18eb1c5a-adviserfinancial54-80f2.l.aivencloud.com'; // Remplacer par votre hôte Aiven
$cfg['Servers'][$i]['port'] = '18179'; // Remplacer par votre port Aiven
$cfg['Servers'][$i]['user'] = 'avnadmin'; // Remplacer par votre utilisateur Aiven
$cfg['Servers'][$i]['password'] = 'AVNS_CGN0rjVzbogydSu2O4O'; // Remplacer par votre mot de passe Aiven
$cfg['Servers'][$i]['ssl'] = true; // Aiven exige SSL
$cfg['Servers'][$i]['compress'] = false;
$cfg['Servers'][$i]['AllowNoPassword'] = false;
?>
