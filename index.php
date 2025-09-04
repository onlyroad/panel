<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>메인 페이지</title>
    <script src="https://www.gstatic.com/firebasejs/9.6.10/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.6.10/firebase-auth-compat.js"></script>
    <script>
        fetch('firebase-config.php')
            .then(response => response.json())
            .then(firebaseConfig => {
                // Firebase 앱 초기화
                firebase.initializeApp(firebaseConfig);

                firebase.auth().onAuthStateChanged(function(user) {
                    if (user) {
                        // 사용자가 로그인되어 있습니다.
                        document.getElementById('user-info').style.display = 'block';
                        document.getElementById('login-button').style.display = 'none';
                        document.getElementById('user-email').innerText = user.email;
                    } else {
                        // 사용자가 로그아웃되었습니다.
                        document.getElementById('user-info').style.display = 'none';
                        document.getElementById('login-button').style.display = 'block';
                    }
                });
            });

        function logout() {
            firebase.auth().signOut().then(() => {
                // 로그아웃 성공
                window.location.href = 'index.php';
            }).catch((error) => {
                // 오류 발생
                console.error('Logout Error:', error);
            });
        }
    </script>
    <style>
        body {
            font-family: sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            flex-direction: column;
            background-color: #f4f4f9;
        }
        h1 {
            color: #333;
        }
        p {
            color: #666;
        }
        a, button {
            font-size: 1.2em;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
            border: none;
            cursor: pointer;
        }
        a:hover, button:hover {
            background-color: #0056b3;
        }
        #user-info {
            display: none;
            text-align: center;
        }
        #login-button {
            display: none;
        }
    </style>
</head>
<body>
    <h1>메인 페이지</h1>

    <div id="user-info">
        <p>환영합니다, <span id="user-email"></span>님!</p>
        <a href="missing_file_list.php" style="margin-bottom: 10px;">미관리 첨부파일</a>
        <a href="manage_attachments.php" style="margin-bottom: 10px;">첨부파일관리</a>
        <button onclick="logout()">로그아웃</button>
    </div>

    <div id="login-button">
        <p>로그인이 필요합니다.</p>
        <a href="login.php">로그인</a>
    </div>

</body>
</html>