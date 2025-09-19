<?php
echo "<!DOCTYPE html>";
echo "<html lang='fr'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>LoanMaster - Tests QA</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 40px; background: #f4f4f4; }";
echo ".container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }";
echo "h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }";
echo ".test-section { margin: 20px 0; padding: 15px; border-left: 4px solid #3498db; background: #ecf0f1; }";
echo ".status { padding: 5px 10px; border-radius: 4px; margin: 5px 0; }";
echo ".success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }";
echo ".error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }";
echo ".info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }";
echo "</style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";
echo "<h1>🎯 LoanMaster - Tests QA</h1>";
echo "<p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>";

echo "<div class='test-section'>";
echo "<h2>📋 État du Système</h2>";
$php_ok = version_compare(PHP_VERSION, '8.1', '>=');
echo "<div class='status " . ($php_ok ? 'success' : 'error') . "'>";
echo "PHP Version: " . PHP_VERSION . " " . ($php_ok ? "✅" : "❌");
echo "</div>";
echo "<div class='status info'>";
echo "Serveur: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu');
echo "</div>";
echo "<div class='status info'>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'];
echo "</div>";
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>🔍 Extensions PHP</h2>";
$required_extensions = ['mysqli', 'pdo', 'curl', 'json', 'mbstring', 'xml', 'zip', 'intl'];
foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "<div class='status " . ($loaded ? 'success' : 'error') . "'>";
    echo $ext . ": " . ($loaded ? "✅ Chargé" : "❌ Manquant");
    echo "</div>";
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>📁 Structure des Fichiers</h2>";
$files_to_check = [
    'composer.json' => 'Configuration Composer',
    'public/index.php' => 'Point d\'entrée Symfony',
    'src/' => 'Code source',
    'config/' => 'Configuration',
    'templates/' => 'Templates Twig',
    'var/' => 'Répertoire de cache'
];

foreach ($files_to_check as $file => $description) {
    $exists = file_exists($file);
    echo "<div class='status " . ($exists ? 'success' : 'error') . "'>";
    echo $description . " (" . $file . "): " . ($exists ? "✅ Présent" : "❌ Absent");
    echo "</div>";
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>🎮 Tests d'Accès</h2>";
echo "<div class='status info'>";
echo "<strong>Tests disponibles:</strong><br>";
echo "• <a href='?test=phpinfo'>PHPInfo complète</a><br>";
echo "• <a href='?test=files'>Liste des fichiers</a><br>";
echo "• <a href='?test=permissions'>Vérification des permissions</a>";
echo "</div>";

if (isset($_GET['test'])) {
    switch ($_GET['test']) {
        case 'phpinfo':
            echo "<h3>🔧 Configuration PHP</h3>";
            echo "<div style='overflow:auto; max-height:400px; border:1px solid #ddd; padding:10px;'>";
            phpinfo();
            echo "</div>";
            break;
            
        case 'files':
            echo "<h3>📂 Contenu du répertoire</h3>";
            echo "<pre style='background:#f8f9fa; padding:10px; border-radius:4px;'>";
            $files = scandir('.');
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    echo $file . (is_dir($file) ? '/' : '') . "\n";
                }
            }
            echo "</pre>";
            break;
            
        case 'permissions':
            echo "<h3>🔒 Permissions</h3>";
            echo "<pre style='background:#f8f9fa; padding:10px; border-radius:4px;'>";
            $directories = ['.', 'var', 'public', 'config'];
            foreach ($directories as $dir) {
                if (file_exists($dir)) {
                    $perms = fileperms($dir);
                    echo $dir . ': ' . substr(sprintf('%o', $perms), -4) . "\n";
                }
            }
            echo "</pre>";
            break;
    }
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>✨ Prochaines Étapes</h2>";
echo "<ol>";
echo "<li>Vérifier que tous les fichiers requis sont présents</li>";
echo "<li>Installer les dépendances Composer si nécessaire</li>";
echo "<li>Configurer la base de données</li>";
echo "<li>Tester les fonctionnalités de l'application</li>";
echo "</ol>";
echo "</div>";

echo "</div>";
echo "</body>";
echo "</html>";
?>
