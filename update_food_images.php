<?php
/**
 * One-time script: update existing products with food image paths.
 * Run once at: http://localhost/bluemoon/update_food_images.php
 * DELETE this file after running.
 */
require_once 'config.php';

$updates = [
    'classic-veg-burger' => 'assets/images/food/classic-veg-burger.jpg',
    'spicy-chicken-burger' => 'assets/images/food/spicy-chicken-burger.jpg',
    'double-decker-burger' => 'assets/images/food/double-decker-burger.jpg',
    'mushroom-swiss-burger' => 'assets/images/food/mushroom-swiss-burger.jpg',
    'margherita-pizza' => 'assets/images/food/margherita-pizza.jpg',
    'pepperoni-blast' => 'assets/images/food/pepperoni-blast.jpg',
    'paneer-tikka-pizza' => 'assets/images/food/paneer-tikka-pizza.jpg',
    'bbq-chicken-pizza' => 'assets/images/food/bbq-chicken-pizza.jpg',
    'chicken-dum-biryani' => 'assets/images/food/chicken-dum-biryani.jpg',
    'veg-biryani' => 'assets/images/food/veg-biryani.jpg',
    'mutton-biryani' => 'assets/images/food/mutton-biryani.jpg',
    'egg-biryani' => 'assets/images/food/egg-biryani.jpg',
    'veg-fried-rice' => 'assets/images/food/veg-fried-rice.jpg',
    'chicken-chow-mein' => 'assets/images/food/chicken-chow-mein.jpg',
    'chilli-paneer' => 'assets/images/food/chilli-paneer.jpg',
    'manchurian-gravy' => 'assets/images/food/manchurian-gravy.jpg',
    'egg-roll' => 'assets/images/food/egg-roll.jpg',
    'chicken-kathi-roll' => 'assets/images/food/chicken-kathi-roll.jpg',
    'paneer-wrap' => 'assets/images/food/paneer-wrap.jpg',
    'gulab-jamun' => 'assets/images/food/gulab-jamun.jpg',
    'chocolate-lava-cake' => 'assets/images/food/chocolate-lava-cake.jpg',
    'mango-kulfi' => 'assets/images/food/mango-kulfi.jpg',
    'mango-lassi' => 'assets/images/food/mango-lassi.jpg',
    'cold-coffee' => 'assets/images/food/cold-coffee.jpg',
    'fresh-lime-soda' => 'assets/images/food/fresh-lime-soda.jpg',
    'strawberry-shake' => 'assets/images/food/strawberry-shake.jpg',
    'veg-spring-rolls' => 'assets/images/food/veg-spring-rolls.jpg',
    'chicken-tikka' => 'assets/images/food/chicken-tikka.jpg',
    'crispy-paneer-fingers' => 'assets/images/food/crispy-paneer-fingers.jpg',
];

$pdo = $pdo ?? null;
if (!$pdo) {
    // Try to get db connection from config
    if (isset($conn)) {
        // MySQLi
        $updated = 0;
        $failed = 0;
        foreach ($updates as $slug => $img) {
            $stmt = $conn->prepare("UPDATE products SET image = ? WHERE slug = ?");
            $stmt->bind_param("ss", $img, $slug);
            if ($stmt->execute() && $stmt->affected_rows >= 0) {
                $updated++;
            } else {
                $failed++;
            }
            $stmt->close();
        }
    }
} else {
    // PDO
    $updated = 0;
    $failed = 0;
    $stmt = $pdo->prepare("UPDATE products SET image = ? WHERE slug = ?");
    foreach ($updates as $slug => $img) {
        try {
            $stmt->execute([$img, $slug]);
            $updated++;
        } catch (Exception $e) {
            $failed++;
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Food Images Update</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #111;
            color: #eee;
            padding: 30px;
        }

        h2 {
            color: #f0a500;
        }

        .ok {
            color: #4caf50;
        }

        .fail {
            color: #f44336;
        }

        .note {
            background: #1a1a2e;
            padding: 15px;
            border-left: 4px solid #f0a500;
            margin-top: 20px;
            border-radius: 4px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }

        th,
        td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #333;
        }

        th {
            background: #1e1e3f;
            color: #f0a500;
        }

        img.thumb {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
        }
    </style>
</head>

<body>
    <h2>🍽️ Food Images Update</h2>
    <p class="ok">✅ Updated: <?= $updated ?> products</p>
    <?php if ($failed > 0): ?>
        <p class="fail">❌ Failed: <?= $failed ?> products</p>
    <?php endif; ?>

    <table>
        <tr>
            <th>Slug</th>
            <th>Image Path</th>
            <th>Preview</th>
            <th>File Exists?</th>
        </tr>
        <?php foreach ($updates as $slug => $img):
            $fullPath = __DIR__ . '/' . $img;
            $exists = file_exists($fullPath);
            ?>
            <tr>
                <td><?= htmlspecialchars($slug) ?></td>
                <td><?= htmlspecialchars($img) ?></td>
                <td><?php if ($exists): ?><img class="thumb" src="<?= htmlspecialchars($img) ?>"
                            alt="<?= htmlspecialchars($slug) ?>"><?php else: ?>—<?php endif; ?></td>
                <td><?= $exists ? '<span class="ok">✅ Yes</span>' : '<span class="fail">❌ Missing</span>' ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <div class="note">
        ⚠️ <strong>Delete this file after running!</strong> It should only be used once.
    </div>
</body>

</html>