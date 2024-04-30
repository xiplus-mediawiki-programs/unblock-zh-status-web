<?php
date_default_timezone_set('Asia/Taipei');
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

const BACKLOG_COUNT = 10;

$int = new Krinkle\Intuition\Intuition([
  'domain' => 'unblock-zh-status-web',
]);

if (isset($_GET['userlang'])) {
  $int->setCookie('userlang', $_GET['userlang']);
}

$int->registerDomain('unblock-zh-status-web', __DIR__ . '/i18n');

// read json
$file = @file_get_contents(__DIR__ . '/latest_time.json');
$total = -1;

function normalEmail($email)
{
  return strtolower(trim($email));
}

$data = null;
if ($file !== false) {
  $data = json_decode($file, true);
}
if ($data !== null) {
  $updated_at = $data['updated_at'];
  $total = count($data['list']);

  $duration_sum = 0;
  $duration_cnt = 0;
  for ($i = 0; $i < min(BACKLOG_COUNT, $total); $i++) {
    $duration_sum += time() - strtotime($data['list'][$i][1]);
    $duration_cnt++;
  }
  $duration_sum /= $duration_cnt;
  $backlog_str = date('Y-m-d H:i:s', time() - $duration_sum);

  $email = '';
  if (isset($_POST['email'])) {
    $email = normalEmail($_POST['email']);
  }
  if ($email) {
    $order = -1;
    $request_time = '';
    foreach ($data['list'] as $index => $value) {
      if ($email === normalEmail($value[0])) {
        $order = $index + 1;
        $request_time = $value[1];
        break;
      }
    }
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    $ua = $_SERVER['HTTP_USER_AGENT'];
    file_put_contents(__DIR__ . '/log.csv', sprintf('%s,%s,%d,%s,%s,"%s"', date('Y-m-d H:i:s'), $email, $order, $request_time, $ip, $ua) . PHP_EOL, FILE_APPEND);
  }
}

$stats_str = json_encode($data['statistics']);
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>
    <?= $int->msg('title', ['variables' => [SITE_NAME]]) ?>
  </title>
  <link href="static/bootstrap.css" rel="stylesheet">
</head>

<body>
  <header>
    <nav class="navbar bg-body-tertiary">
      <div class="container">
        <a class="navbar-brand">
          <?= $int->msg('header', ['variables' => [SITE_NAME]]) ?>
        </a>
        <form class="d-flex" role="search">
          <div class="dropdown">
            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-translate" viewBox="0 0 16 16">
                <path d="M4.545 6.714 4.11 8H3l1.862-5h1.284L8 8H6.833l-.435-1.286H4.545zm1.634-.736L5.5 3.956h-.049l-.679 2.022H6.18z" />
                <path d="M0 2a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v3h3a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-3H2a2 2 0 0 1-2-2V2zm2-1a1 1 0 0 0-1 1v7a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H2zm7.138 9.995c.193.301.402.583.63.846-.748.575-1.673 1.001-2.768 1.292.178.217.451.635.555.867 1.125-.359 2.08-.844 2.886-1.494.777.665 1.739 1.165 2.93 1.472.133-.254.414-.673.629-.89-1.125-.253-2.057-.694-2.82-1.284.681-.747 1.222-1.651 1.621-2.757H14V8h-3v1.047h.765c-.318.844-.74 1.546-1.272 2.13a6.066 6.066 0 0 1-.415-.492 1.988 1.988 0 0 1-.94.31z" />
              </svg>
              <?= $int->getLangName() ?>
            </button>
            <ul class="dropdown-menu">
              <?php
              foreach ($int->getAvailableLangs() as $langCode => $langName) {
                ?>
                <li><a class="dropdown-item" href="?userlang=<?= $langCode ?>"><?= $langName ?></a></li>
                <?php
              }
              ?>
            </ul>
          </div>
        </form>
      </div>
    </nav>
  </header>

  <main class="flex-shrink-0">
    <div class="container">
      <?php
      if ($total === -1) {
        ?>
        <div class="alert alert-danger" role="alert">
          <h4 class="alert-heading">
            <?= $int->msg('status-missing') ?>
          </h4>
        </div>
        <?php
      } else {
        ?>
        <p>
          <?= $int->msg('total-requests', ['variables' => [$total, $updated_at]]) ?>
        </p>
        <div style="height: 300px;"><canvas id="chart"></canvas></div>
        <h3 class="mt-5">
          <?= $int->msg('check-your-request') ?>
        </h3>
        <form action="" method="post">
          <div class="mb-3">
            <label for="inputEmail" class="form-label">
              <?= $int->msg('email-address') ?>
            </label>
            <input type="email" class="form-control" id="inputEmail" name="email" placeholder="name@example.com" autocomplete="email" required>
          </div>
          <div>
            <button type="submit" class="btn btn-primary mb-3">
              <?= $int->msg('check') ?>
            </button>
          </div>
        </form>
        <p>
          <?php if ($email) {
            if ($order === -1) {
              ?>
            <div class="alert alert-danger" role="alert">
              <h4 class="alert-heading">
                <?= $int->msg('request-not-received', ['variables' => [htmlentities($email)]]) ?>
              </h4>
              <p>
                <?= $int->msg('possible-reason') ?>
              <ul>
                <li>
                  <?= $int->msg('not-updated') ?>
                </li>
                <li>
                  <?= $int->msg('mail-holded') ?>
                </li>
                <li>
                  <?= $int->msg('wrong-email') ?>
                </li>
                <li>
                  <?= $int->msg('replied-mail') ?>
                </li>
                <li>
                  <?= $int->msg('ignored-mail', ['variables' => ['https://zh.wikipedia.org/wiki/WP:IPBEMAIL']]) ?>
                </li>
              </ul>
              </p>
            </div>
            <?php
            } else {
              ?>
            <div class="alert alert-success" role="alert">
              <h4 class="alert-heading">
                <?= $int->msg('request-received', ['variables' => [htmlentities($email)]]) ?>
              </h4>
              <p>
                <?= $int->msg('request-received-detail', ['variables' => [$request_time, $order]]) ?>
              </p>
              <hr>
              <p>
                <?= $int->msg('do-not-resend') ?>
              </p>
            </div>
            <?php
            }
          }
          ?>
        </p>
        <?php
      }
      ?>
    </div>
  </main>

  <script src="static/bootstrap.bundle.min.js"></script>
  <script src="static/chart.umd.js"></script>
  <script>
    if (window.history.replaceState) {
      window.history.replaceState(null, null, window.location.href);
    }

    var backlog_str = '<?= $backlog_str ?>';
    var stats_str = '<?= $stats_str ?>';
    var stats = JSON.parse(stats_str);
    var backlog = new Date(backlog_str);
    function updateTime() {
      var duration = (new Date() - backlog) / 1000;
      var day = Math.floor(duration / 86400);
      var hour = (Math.floor(duration / 3600) % 24).toString().padStart(2, '0');
      var minute = (Math.floor(duration / 60) % 60).toString().padStart(2, '0');
      var second = (Math.floor(duration) % 60).toString().padStart(2, '0');
      document.getElementById('dd').innerHTML = day;
      document.getElementById('dt').innerHTML = hour + ':' + minute + ':' + second;
    }
    updateTime();
    setInterval(updateTime, 1000);

    const ctx = document.getElementById('chart');
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: stats.map((item) => item.label),
        datasets: [{
          label: '已處理',
          data: stats.map((item) => item.done),
        }, {
          label: '未處理',
          data: stats.map((item) => item.new),
        }]
      },
      options: {
        scales: {
          x: {
            stacked: true,
          },
          y: {
            stacked: true
          }
        }
      }
    });
  </script>
</body>

</html>
