<?php
declare(strict_types=1);

session_start();

const MAX_FALLOS = 7;
const DICCIONARIO_PATH = __DIR__ . '/data/spanish-words.json';

function normalizar(string $texto): string
{
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    $mapa = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
    ];

    return strtr($texto, $mapa);
}

function diccionario(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    if (!file_exists(DICCIONARIO_PATH)) {
        return $cache = [];
    }

    $contenido = file_get_contents(DICCIONARIO_PATH);
    if ($contenido === false) {
        return $cache = [];
    }

    $lista = json_decode($contenido, true);
    if (!is_array($lista)) {
        return $cache = [];
    }

    $filtradas = [];
    foreach ($lista as $palabra) {
        if (!is_string($palabra)) {
            continue;
        }
        $limpia = trim($palabra);
        if ($limpia === '' || mb_strlen($limpia, 'UTF-8') < 4) {
            continue;
        }
        if (!preg_match('/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+$/u', $limpia)) {
            continue;
        }
        $filtradas[] = $limpia;
    }

    return $cache = $filtradas;
}

function elegirPalabra(): string
{
    $lista = diccionario();
    if (empty($lista)) {
        return 'ahorcado';
    }
    return $lista[random_int(0, count($lista) - 1)];
}

function nuevaPartida(): void
{
    $_SESSION['palabra'] = elegirPalabra();
    $_SESSION['aciertos'] = [];
    $_SESSION['fallos'] = [];
    $_SESSION['mensaje'] = 'Nueva partida iniciada.';
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
}

function estadoPartida(): array
{
    if (!isset($_SESSION['palabra'], $_SESSION['aciertos'], $_SESSION['fallos'], $_SESSION['csrf'])) {
        nuevaPartida();
    }

    $palabra = (string)$_SESSION['palabra'];
    $aciertos = array_values(array_unique((array)$_SESSION['aciertos']));
    $fallos = array_values(array_unique((array)$_SESSION['fallos']));

    $normalPalabra = normalizar($palabra);
    $letrasObjetivo = [];
    foreach (preg_split('//u', $normalPalabra, -1, PREG_SPLIT_NO_EMPTY) as $l) {
        if (preg_match('/^[a-zñ]$/u', $l)) {
            $letrasObjetivo[$l] = true;
        }
    }

    $ganado = !array_diff(array_keys($letrasObjetivo), $aciertos);
    $perdido = count($fallos) >= MAX_FALLOS;

    return [
        'palabra' => $palabra,
        'aciertos' => $aciertos,
        'fallos' => $fallos,
        'mensaje' => (string)($_SESSION['mensaje'] ?? ''),
        'csrf' => (string)$_SESSION['csrf'],
        'ganado' => $ganado,
        'perdido' => $perdido,
        'terminado' => $ganado || $perdido,
    ];
}

function procesarIntento(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $accion = $_POST['accion'] ?? '';
    $token = $_POST['csrf'] ?? '';

    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)$token)) {
        $_SESSION['mensaje'] = 'Solicitud invalida.';
        return;
    }

    if ($accion === 'nueva') {
        nuevaPartida();
        return;
    }

    $estado = estadoPartida();
    if ($estado['terminado']) {
        $_SESSION['mensaje'] = 'La partida termino. Inicia una nueva.';
        return;
    }

    $entrada = normalizar((string)($_POST['letra'] ?? ''));
    if (!preg_match('/^[a-zñ]$/u', $entrada)) {
        $_SESSION['mensaje'] = 'Introduce solo una letra valida.';
        return;
    }

    if (in_array($entrada, $_SESSION['aciertos'], true) || in_array($entrada, $_SESSION['fallos'], true)) {
        $_SESSION['mensaje'] = 'Esa letra ya fue usada.';
        return;
    }

    $normalPalabra = normalizar((string)$_SESSION['palabra']);
    if (str_contains($normalPalabra, $entrada)) {
        $_SESSION['aciertos'][] = $entrada;
        $_SESSION['mensaje'] = 'Bien, la letra esta en la palabra.';
    } else {
        $_SESSION['fallos'][] = $entrada;
        $_SESSION['mensaje'] = 'No aparece esa letra.';
    }
}

function palabraOculta(string $palabra, array $aciertos): string
{
    $chars = preg_split('//u', $palabra, -1, PREG_SPLIT_NO_EMPTY);
    $out = [];

    foreach ($chars as $char) {
        $n = normalizar($char);
        if (preg_match('/^[a-zñ]$/u', $n)) {
            $out[] = in_array($n, $aciertos, true) ? $char : '_';
        } else {
            $out[] = $char;
        }
    }

    return implode(' ', $out);
}

function progreso(array $estado): int
{
    $normalPalabra = normalizar($estado['palabra']);
    $total = 0;
    $descubiertas = 0;

    foreach (preg_split('//u', $normalPalabra, -1, PREG_SPLIT_NO_EMPTY) as $l) {
        if (!preg_match('/^[a-zñ]$/u', $l)) {
            continue;
        }
        $total++;
        if (in_array($l, $estado['aciertos'], true)) {
            $descubiertas++;
        }
    }

    return $total > 0 ? (int)floor(($descubiertas / $total) * 100) : 0;
}

if (!isset($_SESSION['palabra'])) {
    nuevaPartida();
}

procesarIntento();
$estado = estadoPartida();

if ($estado['ganado']) {
    $_SESSION['mensaje'] = 'Ganaste!';
}
if ($estado['perdido']) {
    $_SESSION['mensaje'] = 'Perdiste. La palabra era: ' . $estado['palabra'];
}

$estado = estadoPartida();
$progreso = progreso($estado);
$oculta = palabraOculta($estado['palabra'], $estado['aciertos']);
$teclado = str_split('abcdefghijklmnñopqrstuvwxyz');

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ahorcado PHP</title>
    <style>
        :root {
            --bg: radial-gradient(circle at 20% 10%, #f3b6ff 0%, transparent 30%), radial-gradient(circle at 80% 20%, #7dd3fc 0%, transparent 25%), linear-gradient(140deg, #111827, #0b1020 70%);
            --panel: rgba(17, 24, 39, 0.88);
            --text: #f9fafb;
            --muted: #cbd5e1;
            --ok: #22c55e;
            --bad: #ef4444;
            --accent: #f59e0b;
            --line: rgba(255, 255, 255, 0.14);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text);
            display: grid;
            place-items: center;
            padding: 1rem;
        }
        .panel {
            width: min(920px, 100%);
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 1.2rem;
            backdrop-filter: blur(6px);
        }
        h1 { margin: 0 0 .3rem; font-size: clamp(1.2rem, 2.3vw, 2rem); }
        .sub { margin: 0 0 1rem; color: var(--muted); }
        .pill-wrap { display: flex; gap: .6rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .pill { border: 1px solid var(--line); padding: .35rem .6rem; border-radius: 999px; }
        .pill.ok { border-color: var(--ok); color: var(--ok); }
        .pill.bad { border-color: var(--bad); color: var(--bad); }
        .word { font-size: clamp(1.1rem, 2.1vw, 1.9rem); letter-spacing: .2rem; margin: 1rem 0; }
        form.main { display: flex; gap: .6rem; flex-wrap: wrap; align-items: center; }
        input[type="text"] {
            width: 78px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #0f172a;
            color: var(--text);
            padding: .5rem;
            font-size: 1.2rem;
            text-align: center;
        }
        button {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #111827;
            color: var(--text);
            padding: .55rem .8rem;
            cursor: pointer;
        }
        button:hover { border-color: var(--accent); }
        .kbd { margin-top: 1rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(38px, 1fr)); gap: .35rem; }
        .kbd button { padding: .45rem 0; }
        .hit { border-color: var(--ok) !important; color: var(--ok) !important; }
        .miss { border-color: var(--bad) !important; color: var(--bad) !important; }
        .msg { margin-top: .8rem; color: var(--muted); min-height: 1.2rem; }
        .fails { margin-top: .5rem; color: #fecaca; }
    </style>
</head>
<body>
    <main class="panel">
        <h1>Ahorcado PHP con diccionario completo</h1>
        <p class="sub">Juego en servidor con sesion y validacion de acentos.</p>

        <div class="pill-wrap">
            <div class="pill <?= $estado['ganado'] ? 'ok' : ($estado['perdido'] ? 'bad' : '') ?>">
                <?= $estado['ganado'] ? 'Ganaste' : ($estado['perdido'] ? 'Perdiste' : 'En juego') ?>
            </div>
            <div class="pill">Fallos: <?= count($estado['fallos']) ?>/<?= MAX_FALLOS ?></div>
            <div class="pill">Progreso: <?= $progreso ?>%</div>
        </div>

        <div class="word" aria-label="palabra oculta"><?= htmlspecialchars($oculta, ENT_QUOTES, 'UTF-8') ?></div>

        <form class="main" method="post" action="/" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($estado['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="accion" value="probar">
            <label for="letra">Tu letra</label>
            <input id="letra" name="letra" type="text" maxlength="1" <?= $estado['terminado'] ? 'disabled' : '' ?>>
            <button type="submit" <?= $estado['terminado'] ? 'disabled' : '' ?>>Probar</button>
        </form>

        <form method="post" action="/" style="margin-top:.5rem;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($estado['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="accion" value="nueva">
            <button type="submit">Nueva partida</button>
        </form>

        <section class="kbd" aria-label="teclado en pantalla">
            <?php foreach ($teclado as $l): ?>
                <?php
                    $class = '';
                    if (in_array($l, $estado['aciertos'], true)) { $class = 'hit'; }
                    if (in_array($l, $estado['fallos'], true)) { $class = 'miss'; }
                ?>
                <form method="post" action="/" style="display:contents;">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($estado['csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="accion" value="probar">
                    <input type="hidden" name="letra" value="<?= $l ?>">
                    <button type="submit" class="<?= $class ?>" <?= $estado['terminado'] || $class !== '' ? 'disabled' : '' ?>><?= $l ?></button>
                </form>
            <?php endforeach; ?>
        </section>

        <p class="msg"><?= htmlspecialchars($estado['mensaje'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php if (!empty($estado['fallos'])): ?>
            <p class="fails">Falladas: <?= htmlspecialchars(implode(', ', $estado['fallos']), ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </main>
</body>
</html>
