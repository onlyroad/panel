<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>로그인</title>
    <script src="https://www.gstatic.com/firebasejs/9.6.10/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.6.10/firebase-auth-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/ui/6.0.1/firebase-ui-auth.js"></script>
    <link type="text/css" rel="stylesheet" href="https://www.gstatic.com/firebasejs/ui/6.0.1/firebase-ui-auth.css" />
    <script>
        fetch('firebase-config.php')
            .then(response => response.json())
            .then(firebaseConfig => {
                // Firebase 앱 초기화
                firebase.initializeApp(firebaseConfig);

                // FirebaseUI 위젯 구성
                const uiConfig = {
                    callbacks: {
                        signInSuccessWithAuthResult: function(authResult, redirectUrl) {
                            // 사용자 정보를 서버로 전송
                            fetch('handle_login.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    uid: authResult.user.uid,
                                    email: authResult.user.email,
                                    name: authResult.user.displayName,
                                    photo: authResult.user.photoURL
                                }),
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    // 로그인 성공 및 인증된 사용자, index.php로 리디렉션
                                    window.location.assign('index.php');
                                } else if (data.status === 'unauthenticated') {
                                    // 미인증 사용자
                                    alert('미인증 회원입니다. 관리자에게 문의하세요.');
                                    firebase.auth().signOut();
                                } else {
                                    // 기타 오류
                                    alert('로그인 처리 중 오류가 발생했습니다: ' + data.message);
                                    firebase.auth().signOut();
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('로그인 처리 중 오류가 발생했습니다.');
                                firebase.auth().signOut();
                            });
                            // 자동 리디렉션을 방지하기 위해 false를 반환
                            return false;
                        }
                    },
                    signInSuccessUrl: 'index.php',
                    signInOptions: [
                        firebase.auth.GoogleAuthProvider.PROVIDER_ID,
                        firebase.auth.EmailAuthProvider.PROVIDER_ID
                    ],
                    // 이용 약관 URL
                    tosUrl: '<your-tos-url>',
                    // 개인정보처리방침 URL
                    privacyPolicyUrl: '<your-privacy-policy-url>'
                };

                // FirebaseUI 인스턴스 생성
                const ui = new firebaseui.auth.AuthUI(firebase.auth());
                // 로그인 UI 렌더링
                ui.start('#firebaseui-auth-container', uiConfig);
            })
            .catch(error => console.error('Error loading Firebase config:', error));
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
    </style>
</head>
<body>
    <h1>로그인</h1>
    <div id="firebaseui-auth-container"></div>
</body>
</html>