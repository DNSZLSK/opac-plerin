<#
    deploy-demo.ps1 - Deploiement de la refonte OPAC vers la PRODUCTION opacplerin.fr.

    Principe : upload RECURSIF de dossiers complets (put -r) via SFTP, jamais
    fichier par fichier. Un dossier part d'un bloc : plus de fichier PHP tronque
    qui casse tout le site. Verification de sante apres coup (le front doit
    repondre 200 sans "erreur critique"), avec consigne de rollback si KO.

    Cible : la refonte est en ligne sur opacplerin.fr (servie par le dossier
    /home/opacpler/demo/). Il n'y a plus de recette separee, donc TOUT
    deploiement vise la prod : banniere rouge + confirmation OUI systematiques.
    Le nom du fichier reste "deploy-demo" par habitude ; le dossier distant
    s'appelle encore "demo" (la demo a ete promue en prod par repointage OVH).

    Emplacement : wp-content/tools/ , donc VERSIONNE dans le depot (dont la
    racine est wp-content). Il n'est pas televerse sur OVH pour autant : les
    cibles de deploiement sont listees explicitement plus bas, et tools/ n'en
    fait pas partie. Le script se resout par rapport a lui-meme, il fonctionne
    donc depuis n'importe quel clone du depot.

    Usage (depuis n'importe ou) :
        powershell -ExecutionPolicy Bypass -File "<depot>/tools/deploy-demo.ps1"
        powershell -ExecutionPolicy Bypass -File "<depot>/tools/deploy-demo.ps1" -PluginOnly

    Prerequis : client OpenSSH (sftp) installe, et un acces SFTP OVH. Le mot
    de passe est demande a chaque execution, rien n'est stocke ici.

    ATTENTION : ce script ne fait que POUSSER LE CODE (plugin opac-custom,
    theme opac-theme en entier, .htaccess de wp-content). Il ne migre NI la
    base de donnees, NI les uploads, et
    ne reecrit PAS les URLs demo.opacplerin.fr -> opacplerin.fr en base. La
    bascule du domaine (OVH/DNS) et la base restent a faire a cote.

    Le mot de passe SFTP est demande une fois par OpenSSH (rien n'est stocke).
#>

[CmdletBinding()]
param(
    # Ne deployer que le plugin (raccourci quand seul opac-custom a change).
    [switch]$PluginOnly,
    # Conserve pour compatibilite : depuis la mise en ligne, tout deploiement
    # vise la prod opacplerin.fr, ce switch n'a donc plus d'effet.
    [switch]$Prod,
    # Sauter la confirmation interactive (a utiliser en connaissance de cause).
    [switch]$Yes
)

$ErrorActionPreference = 'Stop'

# --- Configuration -------------------------------------------------------
$SftpHost   = 'ftp.cluster115.hosting.ovh.net'
$SftpUser   = 'opacpler'
$SftpPort   = 22
# Branche d'integration : c'est elle qui accumule le travail, donc la seule
# reference qui rende le controle des branches oubliees lisible (cf. plus bas).
$IntegrationBranch = 'develop'
# Chemin derive de l'emplacement du script (pas de chemin absolu code en dur) :
# le script vit dans wp-content/tools/, donc wp-content est son dossier parent.
# N'importe quel dev peut donc cloner le depot ou il veut, le script suit.
$LocalWpContent = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path -replace '\\', '/'

# --- Cible unique : PRODUCTION opacplerin.fr -----------------------------
# La refonte est en ligne sur opacplerin.fr, servie par le dossier
# /home/opacpler/demo/ (la demo a ete promue en prod par repointage du domaine
# cote OVH : les fichiers n'ont pas bouge, seul le domaine qui pointe dessus a
# change). Il n'y a plus de recette separee : demo.opacplerin.fr sert desormais
# l'ancien site. On deploie donc TOUJOURS vers ce dossier et on verifie TOUJOURS
# opacplerin.fr. Le switch -Prod est conserve pour ne pas casser d'habitude mais
# n'a plus d'effet : tout deploiement est un deploiement de production.
$EnvName    = 'PRODUCTION'
$RemoteBase = '/home/opacpler/demo/wp-content'
$SiteBase   = 'https://opacplerin.fr'

# Pages de controle : le front doit repondre 200 sans "erreur critique".
$HealthUrls = @(
    "$SiteBase/",
    "$SiteBase/ateliers/"
)
# Assets statiques, verifies separement des pages. Indispensable : un
# .htaccess illisible dans wp-content/ met TOUT le statique en 403 pendant
# que les pages HTML continuent de repondre 200. Sans ce controle, le script
# annoncerait "DEPLOIEMENT OK" sur un site sans aucun CSS ni image.
$AssetUrls = @(
    "$SiteBase/wp-content/themes/opac-theme/assets/css/opac.css",
    "$SiteBase/wp-content/themes/opac-theme/assets/js/opac.js"
)

# Cibles de deploiement : (dossier local parent, dossier a pousser, dossier distant parent)
# On pousse des DOSSIERS entiers en recursif, pas des fichiers isoles.
$Targets = @(
    @{ LocalParent = "$LocalWpContent/plugins";            Dir = 'opac-custom'; RemoteParent = "$RemoteBase/plugins" }
)
if (-not $PluginOnly) {
    $Targets += @{ LocalParent = "$LocalWpContent/themes/opac-theme"; Dir = 'templates'; RemoteParent = "$RemoteBase/themes/opac-theme" }
    # assets/ = opac.css, opac.js et les polices. Absent de la liste jusqu'au
    # 2026-07-27 : toute modif de CSS, de JS ou de police n'etait donc jamais
    # deployee par ce script.
    $Targets += @{ LocalParent = "$LocalWpContent/themes/opac-theme"; Dir = 'assets';    RemoteParent = "$RemoteBase/themes/opac-theme" }
    # parts/ (header, footer) et patterns/ : absents de la liste jusqu'au
    # 2026-09-13, meme oubli que assets/ avant le 2026-07-27. Consequence
    # constatee en ligne : la prod servait encore le header d'avant le passage
    # du menu "S'inscrire" en <details> natif (accessibilite sans JavaScript),
    # alors que le CSS et le JS correspondants, eux, etaient bien deployes.
    # Le theme est desormais couvert en entier.
    $Targets += @{ LocalParent = "$LocalWpContent/themes/opac-theme"; Dir = 'parts';     RemoteParent = "$RemoteBase/themes/opac-theme" }
    $Targets += @{ LocalParent = "$LocalWpContent/themes/opac-theme"; Dir = 'patterns';  RemoteParent = "$RemoteBase/themes/opac-theme" }
}

# Fichiers isoles. Exception assumee au principe "dossiers entiers" ci-dessus :
# ces fichiers n'appartiennent a aucun dossier deployable.
#
# Chaque entree porte sa propre destination, car ils ne vivent pas au meme
# endroit : .htaccess est a la racine de wp-content, les trois autres a la
# racine du theme. Sans .htaccess, le cache navigateur reste a 15 minutes.
# functions.php, theme.json et style.css n'etaient couverts par aucune cible
# jusqu'au 2026-09-13 : toute modification y restait locale.
$SingleFiles = @()
if (-not $PluginOnly) {
    $SingleFiles += @{ LocalParent = "$LocalWpContent"; File = '.htaccess';     RemoteParent = $RemoteBase }
    $SingleFiles += @{ LocalParent = "$LocalWpContent/themes/opac-theme"; File = 'functions.php'; RemoteParent = "$RemoteBase/themes/opac-theme" }
    $SingleFiles += @{ LocalParent = "$LocalWpContent/themes/opac-theme"; File = 'theme.json';    RemoteParent = "$RemoteBase/themes/opac-theme" }
    $SingleFiles += @{ LocalParent = "$LocalWpContent/themes/opac-theme"; File = 'style.css';     RemoteParent = "$RemoteBase/themes/opac-theme" }
}

# --- Verifications preliminaires -----------------------------------------
$sftp = (Get-Command sftp -ErrorAction SilentlyContinue)
if (-not $sftp) {
    Write-Host "ERREUR : 'sftp' (OpenSSH) introuvable. Installe-le via 'Fonctionnalites facultatives > Client OpenSSH'." -ForegroundColor Red
    exit 1
}

foreach ($t in $Targets) {
    $local = Join-Path $t.LocalParent $t.Dir
    if (-not (Test-Path $local)) {
        Write-Host "ERREUR : dossier local introuvable : $local" -ForegroundColor Red
        exit 1
    }
}
foreach ($f in $SingleFiles) {
    $local = Join-Path $f.LocalParent $f.File
    if (-not (Test-Path $local)) {
        Write-Host "ERREUR : fichier local introuvable : $local" -ForegroundColor Red
        exit 1
    }
}

# Garde-fou : on ne deploie que depuis la bonne branche git (feature ou develop),
# et on affiche laquelle, pour ne pas pousser un WIP par erreur.
#
# $unmerged repond a la question que "git status" ne pose pas : qu'est-ce qui
# existe dans le depot mais PAS dans la branche qui accumule le travail. Une
# branche restee de cote ne salit pas l'arbre, donc rien ne la signale, et le
# deploiement part sans elle. C'est ce qui a fait disparaitre le champ animateur
# de la demo pendant plusieurs jours, avec un arbre propre a chaque fois.
#
# La comparaison se fait contre $IntegrationBranch et NON contre la branche
# courante : mesuree depuis une branche feature, "non fusionnee" remonterait les
# 80 autres branches du depot, toutes sans rapport. Un avertissement qu'on
# apprend a ignorer ne protege de rien.
Push-Location $LocalWpContent
try {
    $branch = (git rev-parse --abbrev-ref HEAD 2>$null)
    $dirty  = (git status --porcelain 2>$null)
    # TrimStart : git prefixe la branche courante d'une etoile.
    $unmerged = @(git branch --no-merged $IntegrationBranch 2>$null |
        ForEach-Object { $_.TrimStart('*', ' ').Trim() } |
        Where-Object { $_ -ne '' -and $_ -ne $IntegrationBranch })
} catch {
    $branch   = '(git indisponible)'
    $dirty    = $null
    $unmerged = @()
}
Pop-Location

Write-Host "=== Deploiement OPAC : $EnvName ===" -ForegroundColor Red
Write-Host "  *** CIBLE DE PRODUCTION ($SiteBase) - SITE PUBLIC EN LIGNE ***" -ForegroundColor Red
Write-Host "  Hote      : $SftpUser@${SftpHost}:$SftpPort"
Write-Host "  Dossier   : $RemoteBase"
Write-Host "  Branche   : $branch"
Write-Host "  Cibles    :"
foreach ($t in $Targets) {
    Write-Host ("    - {0}/  ->  {1}/{2}/" -f (Join-Path $t.LocalParent $t.Dir), $t.RemoteParent, $t.Dir)
}
foreach ($f in $SingleFiles) {
    Write-Host ("    - {0}   ->  {1}/{2}" -f (Join-Path $f.LocalParent $f.File), $f.RemoteParent, $f.File)
}

# Ce script envoie l'etat du DISQUE, pas celui d'un commit. Si l'arbre de
# travail est sale, du code non commite part en ligne sans qu'on le sache.
if ($dirty) {
    Write-Host "`n  ATTENTION : modifications non commitees dans wp-content." -ForegroundColor Yellow
    Write-Host "  Elles seront deployees telles quelles :" -ForegroundColor Yellow
    $dirty -split "`n" | Select-Object -First 10 | ForEach-Object { Write-Host "    $_" -ForegroundColor Yellow }
}

# Le symetrique du controle ci-dessus : ce qui manque, plutot que ce qui traine.
if ($unmerged.Count -gt 0) {
    Write-Host "`n  ATTENTION : $($unmerged.Count) branche(s) non fusionnee(s) dans '$IntegrationBranch'." -ForegroundColor Yellow
    Write-Host "  Leur travail NE SERA PAS deploye, meme si l'arbre est propre :" -ForegroundColor Yellow
    $unmerged | Select-Object -First 10 | ForEach-Object { Write-Host "    $_" -ForegroundColor Yellow }
    if ($unmerged.Count -gt 10) {
        Write-Host "    ... et $($unmerged.Count - 10) autre(s)" -ForegroundColor Yellow
    }
    Write-Host "  Si l'une d'elles est finie, fusionne-la avant de deployer." -ForegroundColor Yellow
} elseif ($branch -ne '(git indisponible)') {
    Write-Host "  Branches  : aucune branche en attente de fusion dans '$IntegrationBranch'." -ForegroundColor Green
}

if (-not $Yes) {
    $confirm = Read-Host "Confirmer le deploiement ? (tape OUI)"
    if ($confirm -ne 'OUI') { Write-Host "Annule." -ForegroundColor Yellow; exit 0 }
}

# --- Construction du batch SFTP ------------------------------------------
$batchLines = @()
foreach ($t in $Targets) {
    $batchLines += "lcd `"$($t.LocalParent)`""
    $batchLines += "cd $($t.RemoteParent)"
    $batchLines += "put -r $($t.Dir)"
}
# Droits des dossiers crees par "put -r".
#
# OpenSSH cree les repertoires distants en 700 (drwx------). Apache ne peut
# alors pas les TRAVERSER et renvoie 403 sur tout leur contenu, avec un
# message trompeur : "Server unable to read htaccess file, denying access to
# be safe". Il n'y a aucun .htaccess en cause, Apache en cherche simplement un
# dans chaque dossier du chemin et n'arrive meme pas a entrer.
#
# C'est ce qui bloquait plugins/opac-custom/assets/ (donc opac-pwa.js et
# l'icone du manifest) depuis le premier deploiement, et ce qui a mis les
# assets du theme en 403 le 2026-07-27.
#
# La liste est generee depuis l'arborescence LOCALE : un nouveau sous-dossier
# est donc couvert automatiquement, sans rien a maintenir ici.
foreach ($t in $Targets) {
    $localRoot = Join-Path $t.LocalParent $t.Dir
    $dirs = @($t.Dir)
    Get-ChildItem -Path $localRoot -Directory -Recurse | ForEach-Object {
        $rel = $_.FullName.Substring($localRoot.Length).TrimStart('\', '/') -replace '\\', '/'
        $dirs += "$($t.Dir)/$rel"
    }
    $batchLines += "cd $($t.RemoteParent)"
    foreach ($d in $dirs) {
        $batchLines += "-chmod 755 $d"
    }
}

foreach ($f in $SingleFiles) {
    $batchLines += "lcd `"$($f.LocalParent)`""
    $batchLines += "cd $($f.RemoteParent)"
    $batchLines += "put $($f.File)"
    # Droits explicites : un .htaccess qu'Apache ne peut pas LIRE fait
    # tomber tout le repertoire en 403 ("Server unable to read htaccess
    # file, denying access to be safe"). C'est exactement ce qui bloque
    # deja plugins/opac-custom/ sur la demo.
    #
    # Prefixe "-" : en mode batch (sftp -b), la premiere commande en
    # echec avorte tout le deploiement. Si le serveur refuse le chmod, on
    # continue quand meme, le controle de sante en fin de script se
    # chargera de detecter un statique inaccessible.
    $batchLines += "-chmod 644 $($f.File)"
}
$batchLines += "bye"

$batchFile = Join-Path $env:TEMP ("opac-deploy-{0}.sftp" -f (Get-Date -Format 'yyyyMMdd-HHmmss'))
$batchLines | Set-Content -Path $batchFile -Encoding ascii

Write-Host "`n--- Upload en cours (mot de passe SFTP demande une fois) ---" -ForegroundColor Cyan
# -b active le mode batch d'OpenSSH, qui coupe la demande de mot de passe (il
# ne tente que la cle). On force BatchMode=no pour reautoriser le prompt tout
# en gardant l'execution non-interactive des commandes du batch.
& sftp -P $SftpPort -o BatchMode=no -o StrictHostKeyChecking=accept-new -b $batchFile "$SftpUser@$SftpHost"
$sftpExit = $LASTEXITCODE
Remove-Item $batchFile -ErrorAction SilentlyContinue

if ($sftpExit -ne 0) {
    Write-Host "`nECHEC SFTP (code $sftpExit). Rien de garanti cote serveur, relance le script." -ForegroundColor Red
    exit $sftpExit
}
Write-Host "Upload termine." -ForegroundColor Green

# --- Verification de sante -----------------------------------------------
Write-Host "`n--- Verification du site ---" -ForegroundColor Cyan
$allOk = $true
foreach ($url in $HealthUrls) {
    try {
        $resp = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 20
        $bad  = ($resp.Content -match 'erreur critique|critical error|Fatal error')
        if ($resp.StatusCode -eq 200 -and -not $bad) {
            Write-Host ("  OK   {0} (HTTP {1})" -f $url, $resp.StatusCode) -ForegroundColor Green
        } else {
            $allOk = $false
            Write-Host ("  KO   {0} (HTTP {1}, erreur critique detectee: {2})" -f $url, $resp.StatusCode, $bad) -ForegroundColor Red
        }
    } catch {
        $allOk = $false
        $sc = $_.Exception.Response.StatusCode.value__
        Write-Host ("  KO   {0} (echec: HTTP {1} {2})" -f $url, $sc, $_.Exception.Message) -ForegroundColor Red
    }
}

$assetKo = $false
foreach ($url in $AssetUrls) {
    try {
        $resp = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 20
        if ($resp.StatusCode -eq 200) {
            Write-Host ("  OK   {0} (HTTP 200)" -f $url) -ForegroundColor Green
        } else {
            $allOk = $false; $assetKo = $true
            Write-Host ("  KO   {0} (HTTP {1})" -f $url, $resp.StatusCode) -ForegroundColor Red
        }
    } catch {
        $allOk = $false; $assetKo = $true
        $sc = $_.Exception.Response.StatusCode.value__
        Write-Host ("  KO   {0} (HTTP {1}) : statique inaccessible" -f $url, $sc) -ForegroundColor Red
    }
}

if ($allOk) {
    Write-Host "`nDEPLOIEMENT OK. Le site repond normalement." -ForegroundColor Green
} elseif ($assetKo) {
    Write-Host "`nATTENTION : les pages repondent mais le statique est inaccessible." -ForegroundColor Red
    Write-Host "Cause la plus probable : un .htaccess qu'Apache n'arrive pas a lire," -ForegroundColor Yellow
    Write-Host "ce qui fait tomber tout le repertoire en 403. En SFTP :" -ForegroundColor Yellow
    Write-Host "  chmod 644 $RemoteBase/.htaccess" -ForegroundColor Yellow
    Write-Host "  (ou supprime-le pour revenir a l'etat d'avant)" -ForegroundColor Yellow
    exit 2
} else {
    Write-Host "`nATTENTION : le site semble casse apres deploiement." -ForegroundColor Red
    Write-Host "Rollback rapide en SFTP :" -ForegroundColor Yellow
    Write-Host "  cd $RemoteBase/plugins ; rename opac-custom opac-custom-off" -ForegroundColor Yellow
    Write-Host "  (le site revient a l'etat sans plugin, puis on rebascule sur develop et on redeploie)" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "  Si la panne vient du THEME (page blanche, mise en page cassee), renommer" -ForegroundColor Yellow
    Write-Host "  le dossier ne suffit pas : WordPress tomberait sur un theme absent. Il faut" -ForegroundColor Yellow
    Write-Host "  restaurer les fichiers, d'ou la sauvegarde a prendre AVANT un deploiement" -ForegroundColor Yellow
    Write-Host "  complet :  sftp> get -r $RemoteBase/themes/opac-theme" -ForegroundColor Yellow
    exit 2
}
