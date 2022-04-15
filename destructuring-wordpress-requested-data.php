foreach($_POST['data'] as $v) {
  $_POST['data'][$v['name']] = $v['value'];
}
