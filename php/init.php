<?php

require_once __DIR__ . '/vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;

class Pusher extends Plugin implements IHandler {
    const PRIVATE_KEY_PROP = 'privateKey';
    const PUBLIC_KEY_PROP = 'publicKey';
    const SUBSCRIPTION_PROP = 'subscription';
    
    private $plugin_host;

    function about() {
        return array(1.0,
            'Show push notification for new posts',
            'powerivq',
            true,
            'https://github.com/powerivq/ttrss-pusher');
    }

    function init($host) {
        $this->plugin_host = $host;
        $host->add_handler('pusher', 'update_subscription', $this);
        $host->add_handler('pusher', 'mark_read', $this);
        $host->add_filter_action($this, 'push', 'Send push notification to browsers');
        $host->add_hook($host::HOOK_RENDER_ARTICLE, $this);
        $host->add_hook($host::HOOK_RENDER_ARTICLE_CDM, $this);
        $host->add_hook($host::HOOK_FETCH_FEED, $this);
        $host->add_hook($host::HOOK_HOUSE_KEEPING, $this);
    }

    function init_keys() {
        if ($this->plugin_host->get($this, self::PRIVATE_KEY_PROP)
                && $this->plugin_host->get($this, self::PUBLIC_KEY_PROP)) {
            return;
        }

        $keys = VAPID::createVapidKeys();
        $this->plugin_host->set($this, self::PRIVATE_KEY_PROP, $keys[self::PRIVATE_KEY_PROP], false);
        $this->plugin_host->set($this, self::PUBLIC_KEY_PROP, $keys[self::PUBLIC_KEY_PROP]);
    }

    function init_database() {
        $sth = $this->plugin_host->get_pdo()->prepare(file_get_contents(__DIR__ . '/init.sql'));
        $sth->execute([]);
    }

    function get_excerpt_img($html) {
        $ret = [];

        $doc = new DOMDocument();
        if (strpos($html, '<html') === false) {
            $html = '<?xml encoding="utf-8"><html><body>' . $html . '</body></html>';
        }
        $doc->loadHTML($html);
        $xpath = new DOMXPath($doc);

        foreach ($xpath->query('//img') as $img) {
            $src = $img->getAttribute('src');
            if (strlen($src) <= 200) {
                $ret['image'] = $src;
                break;
            }
        }
        if (isset($ret['image']) && strpos($ret['image'], 'http://') === 0) {
            $ret['image'] = getenv('SELF_URL_PATH') . '/public.php?' . http_build_query(array(
                'op' => 'pluginhandler',
                'plugin'=> 'af_proxy_http',
                'pmethod' => 'imgproxy',
                'url' => $ret['image']
            ));
        }

        foreach (['h1', 'h2', 'h3', 'h4', 'h5'] as $tag) {
            foreach ($xpath->query('//' . $tag) as $h) {
                $h->parentNode->removeChild($h);
            }
        }

        $content = $doc->saveHTML($doc->documentElement);
        $content = strip_tags(html_entity_decode($content));
        $content = preg_replace('/^[\p{Z}\s\r\n]+/u', '', $content);
        $content = preg_replace('/[\p{Z}\s\r\n]+/u', ' ', $content);
        $ret['excerpt'] = mb_substr($content, 0, 200);
        return $ret;
    }

    function has_pushed($link) {
        $sha1 = sha1($link);
        $uid = $this->plugin_host->get_owner_uid();
        $find_stmt = $this->plugin_host->get_pdo()->prepare('SELECT EXISTS(
            SELECT * FROM ttrss_pusher WHERE uid=? AND url_hash=?)');
        $find_stmt->execute([$uid, $sha1]);
        $result = $find_stmt->fetch();
        if ($result[0]) {
            $this->plugin_host->get_pdo()->prepare('UPDATE ttrss_pusher
                SET last_accessed=NOW() WHERE uid=? AND url_hash=?')
                ->execute([$uid, $sha1]);
            return true;
        }
        $this->plugin_host->get_pdo()->prepare('INSERT INTO ttrss_pusher
            (url_hash, last_accessed, uid) VALUES (?, NOW(), ?)')
            ->execute([$sha1, $uid]);
        return false;
    }

    function hook_article_filter_action($article, $action) {
        if ($this->has_pushed($article['link'])) return $article;

        $params = array_merge(array(
            'title' => mb_substr($article['title'], 0, 200),
            'link' => mb_substr($article['link'], 0, 500),
            'guid' => $article['guid_hashed'],
            'uid' => $article['owner_uid'],
        ), $this->get_excerpt_img($article['content']));

        $defaultOptions = [
            'TTL' => 600,
        ];

        $auth = [
            'VAPID' => [
                'subject' => 'mailto:noreply@github.com',
                'publicKey' => $this->plugin_host->get($this, self::PUBLIC_KEY_PROP),
                'privateKey' => $this->plugin_host->get($this, self::PRIVATE_KEY_PROP),
            ],
        ];

        $payload = json_encode($params);
        $subscriptions = $this->plugin_host->get($this, self::SUBSCRIPTION_PROP);
        if (!$subscriptions) return $article;
        $webPush = new WebPush($auth, $defaultOptions);
        $webPush->setDefaultOptions($defaultOptions);

        $browser_ids = [];
        foreach ($subscriptions as $browser_id => $subscription) {
            $browser_ids[] = $browser_id;
            $webPush->queueNotification($subscription, $payload);
        }

        $unsub_map = [];
        Debug::log(sprintf('Pushing %s', $article['title']));
        foreach ($webPush->flush() as $index => $report) {
            if ($report->isSubscriptionExpired()
                    || !$report->isSuccess() && strpos($report->getReason(), '401 Unauthorized') !== false) {
                Debug::log('Unsubscribing');
                $unsub_map[$browser_id] = null;
            }
            Debug::log(($report->isSuccess() ? 'Succeed' : 'Failed') . ': ' . $report->getReason());
        }
        if ($unsub_map) {
            $this->set_subscription_for_browser($unsub_map);
        }
        return $article;
    }

    function mark_read() {
        $uid = $_SESSION['uid'];
        $guid = $_POST['guid'];

        $this->plugin_host->get_pdo()->prepare('UPDATE ttrss_user_entries ue
            JOIN ttrss_entries e ON (e.id = ue.ref_id)
            SET ue.unread=0 WHERE e.guid=? AND ue.owner_uid=?')->execute([$guid, $uid]);
    }

    function update_subscription() {
        $subscription_arr = json_decode($_POST['subscription'], true);
        $browser_id = $_POST['browserId'];
        error_log("Subscribing: ". $browser_id);
        $this->set_subscription_for_browser(
            array($browser_id =>
                $subscription_arr ? Subscription::create($subscription_arr) : null
        ));
    }

    function set_subscription_for_browser($browser_id_to_subscription) {
        $subscriptions = $this->plugin_host->get($this, self::SUBSCRIPTION_PROP);
        foreach ($browser_id_to_subscription as $browser_id => $subscription) {
            if ($subscription === null) {
                unset($subscriptions[$browser_id]);
            } else {
                $subscriptions[$browser_id] = $subscription;
            }
        }
        $this->plugin_host->set($this, self::SUBSCRIPTION_PROP, $subscriptions);
    }

    function hide_pusher_tag($article) {
        $tags = array_map('trim', explode(',', $article['tag_cache']));
        $tags = array_diff($tags, ['pusher_sent']);
        $article['tag_cache'] = implode(',', $tags);
        return $article;
    }

    function hook_render_article($article) {
        return $this->hide_pusher_tag($article);
    }

    function hook_render_article_cdm($article) {
        return $this->hide_pusher_tag($article);
    }

    function hook_fetch_feed($feed_data, $fetch_url, $owner_uid, $feed, $num, $auth_login, $auth_pass) {
        $this->init_database();
        return $feed_data;
    }

    function hook_house_keeping() {
        $this->plugin_host->get_pdo()->prepare('DELETE FROM ttrss_pusher 
            WHERE last_accessed<NOW()-INTERVAL 30 DAY')->execute([]);
    }

    function csrf_ignore($method): bool {
        return true;
    }

    function before($method): bool {
        return true;
    }

    function after(): bool {
        return true;
    }

    function get_js() {
        $this->init_keys();
        $publicKey = $this->plugin_host->get($this, self::PUBLIC_KEY_PROP);
        return "window.pusherPublicKey='$publicKey';" . file_get_contents(__DIR__ . "/main.js");
    }

    function api_version() {
        return 2;
    }
}
