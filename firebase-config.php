<?php
header('Content-Type: application/json');

$firebaseConfig = [
    'apiKey' => 'AIzaSyDIxXQVrGSVuaTQuTNjjlXw1eA22ux7EGQ',
    'authDomain' => 'wekoinonia.firebaseapp.com',
    'projectId' => 'wekoinonia',
    'storageBucket' => 'wekoinonia.appspot.com',
    'messagingSenderId' => '663134411881',
    'appId' => '1:663134411881:web:8a3c5af7d607e7cf76d6f8'
];

echo json_encode($firebaseConfig);
?>