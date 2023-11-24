<?php
$slack_api_key = '';
$slack_channel = 'upload_dev';
$uuid = $_COOKIE["uuid"];
$file_title = $_COOKIE["file_title"];
$upload_count = $_COOKIE["upload_count"];
if (isset($_POST['upload_file'])) {
    if (empty($_FILES)) {
        echo '<b>ファイルが選択されていません</b>';
    } else {
        $image = substr(strrchr($_FILES['image']['name'], '.'), 1);
        $file_name = "./upload/$uuid-$upload_count.$image";

        $image = substr(strrchr($_FILES['image']['name'], '.'), 1);
        $file_name = "./upload/$uuid-$upload_count.$image";

        if (!empty($_FILES['image']['name'])) {
            move_uploaded_file($_FILES['image']['tmp_name'], $file_name);
            if (!exif_imagetype($file_name)) {
                echo '<b>アップロードされたデータが画像ファイルではありません</b>';
            }else{
                $name = $_FILES['image']['name'];
                echo "<b>登録済み=>$name</b>";
            }
        }
        setcookie("upload_count", $upload_count + 1, time() + 3600);
    }
} else if (isset($_POST['upload_end'])) {
    # slack投げ込み
    $headers = [
        "Authorization: Bearer $slack_api_key",
        'Content-Type: application/json;charset=utf-8'
    ];

    $url = "https://slack.com/api/chat.postMessage";
    $post_fields = [
        "channel" => $slack_channel,
        "text" => "[Slack-Uploader System] $file_title",
    ];

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($post_fields)
    ];

    $ch = curl_init();
    curl_setopt_array($ch, $options);
    $result = curl_exec($ch);
    curl_close($ch);

    $thread_ts = json_decode($result)->ts;

    $search_path = "./upload/$uuid-*.*";

    foreach (glob(($search_path), GLOB_NOSORT) as $file_path) {
        $headers = [
            "Authorization: Bearer $slack_api_key",
            'Content-Type: multipart/form-data'
        ];
        $url = "https://slack.com/api/files.upload";
        $post_fields = [
            "channels" => $slack_channel,
            "thread_ts" => $thread_ts,
            "initial_comment" => "Uploaded by Slack-Uploader System",
            "file" => new CurlFile($file_path),
            "filename" => basename($file_path),
            'title' => "$upload_count",
        ];
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post_fields
        ];
        print_r($post_fields);
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        curl_close($ch);
    }

    // ファイル削除
    foreach (glob('./upload/*.*') as $key => $value) {
        $date = explode("-", basename($value))[0];
        $year = substr($date, 0, 4);
        $month = substr($date, 4, 2);
        $day = substr($date, 6, 2);
        $time1 = new DateTime(date('Y-m-d'));
        $time2 = new DateTime("$year-$month-$day");
        $diff = $time1->diff($time2);
        echo $diff->format('総日数は%a日');
        if ($diff->format('%a') > 7) {
            $del_result = unlink($value);
        }
        echo $date . '<br>';
    }


//    $del_result = unlink($file);
//    echo($del_result ? 'ファイルを削除しました' : 'ファイルの削除に失敗しました');
//    $upload_count++;
} else if (isset($_POST['post_init'])) {
    $file_title = $_POST['name'];
    $uuid = date('Ymd') . "-" . uniqid();
// initialize
    setcookie("upload_count", 0, time() + 3600);
    setcookie("uuid", $uuid, time() + 3600);
    setcookie("file_title", $_POST['name'], time() + 3600);
}
?>

    <h1>画像アップローダ</h1>
<?php if ($_SERVER["REQUEST_METHOD"] != "POST"): ?>
    <form method="post">
        <h3>タイトル</h3>
        <label for="name"></label>
        <input name="name" id="name" type="text"/>
        <br/>
        <br/>
        <button><input type="submit" name="post_init" value="送信"></button>
    </form>
<?php else: ?>
    <form method="post" enctype="multipart/form-data">
        <p>アップロード済み画像: <?php echo $upload_count; ?></p>
        ファイル名: <?php echo $file_title; ?>
        <br/>
        <br/>
        <input type="file" name="image" accept="image/*" capture="camera" @change="onCaptureImage">
        <button><input type="submit" name="upload_file" value="ファイル送信"></button>
        <button><input type="submit" name="upload_end" value="終了"></button>
    </form>
    <?php if (isset($_POST['upload'])): ?>
    <?php endif; ?>
<?php endif; ?>