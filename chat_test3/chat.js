document.addEventListener('DOMContentLoaded', function() {
    const startSurveyButton = document.getElementById('startSurvey');
    const chatForm = document.getElementById('chatForm');
    const stepButtons = document.querySelectorAll('button[data-next]');

    startSurveyButton.addEventListener('click', handleStartSurvey);
    chatForm.addEventListener('submit', handleSubmitForm);
    stepButtons.forEach(button => {
        button.addEventListener('click', () => {
            nextStep(parseInt(button.getAttribute('data-next')));
        });
    });

    window.addEventListener('beforeunload', handleEndSession); // ページを離れる前にセッションを終了

    function handleStartSurvey() {
        fetch('session_manager.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'start', userId: 1})
        })
        .then(response => response.ok ? response.json() : Promise.reject('Failed to start session'))
        .then(data => {
            if (data.success) {
                sessionStorage.setItem('session_id', data.session_id);
                startSurvey();
            } else {
                console.error('Failed to start session:', data.message);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function startSurvey() {
        document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
        document.getElementById('step1').classList.add('active');
        startSurveyButton.style.display = 'none';
    }

    function nextStep(step) {
        const currentInput = document.getElementById(`step${step - 1}`).querySelector('input, textarea').value;
        if (!validateInput(currentInput)) return;

        updateProgress(step);
        document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
        document.getElementById(`step${step}`).classList.add('active');
    }

    function updateProgress(step) {
        const sessionId = sessionStorage.getItem('session_id');
        if (!sessionId) {
            console.error('Session ID is missing');
            return;
        }
    
        fetch('session_manager.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'update',
                session_id: sessionId,
                step: step
            })
        })
        .then(response => response.ok ? response.json() : Promise.reject('Failed to update the session'))
        .then(data => {
            if (!data.success) {
                // エラーメッセージが存在するかチェックしてからログに出力
                const errorMessage = data.message ? data.message : 'No error message provided';
                console.error('Failed to update session:', errorMessage);
            }
        })
        .catch(error => {
            console.error('Error updating session:', error);
        });
    }

    function handleEndSession() {
        const sessionId = sessionStorage.getItem('session_id');
        if (sessionId) {
            navigator.sendBeacon('session_manager.php', JSON.stringify({
                action: 'end',
                session_id: sessionId
            }));
        }
    }

    function validateInput(input) {
        if (input.trim() === '') {
            alert('このフィールドは必須です。');
            return false;
        }
        return true;
    }

    function handleSubmitForm(event) {
        event.preventDefault();
        const name = document.getElementById('name').value;
        const email = document.getElementById('email').value;
        const firstMessage = document.getElementById('firstMessage').value;

        console.log('Starting chat session...');
        ChannelIO('boot', {
            pluginKey: '748abef7-08de-4b43-a36d-6784199afd77', // 実際のプラグインキーに置き換えてください
            memberId: email,
            profile: {name, email, firstMessage},
        }, (error, user) => {
            if (error) {
                console.error('Boot error:', error);
                alert('チャットの開始に失敗しました。');
                return;
            }
            sendInitialMessage(user.id, firstMessage);
        });
    }

    function sendInitialMessage(userId, message) {
        fetch('registerUser.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({userId, firstMessage: message})
        })
        .then(response => response.ok ? response.json() : Promise.reject('Failed to send message'))
        .then(data => {
            if (data.success) {
                console.log('Chat session created and message sent:', data);
                ChannelIO('openChat', undefined, '');
            } else {
                throw new Error(data.message || 'Failed to start chat session');
            }
        })
        .catch(error => {
            console.error('Error during chat session creation:', error);
            alert('エラーが発生しました。');
        });
    }


    (function() {
        var w = window;
        if (w.ChannelIO) {
            return (window.console.error || window.console.log || function(){})('ChannelIO script included twice.');
        }
        var ch = function() {
            ch.c(arguments);
        };
        ch.q = [];
        ch.c = function(args) {
            ch.q.push(args);
        };
        w.ChannelIO = ch;
        function l() {
            if (w.ChannelIOInitialized) {
                return;
            }
            w.ChannelIOInitialized = true;
            var s = document.createElement('script');
            s.type = 'text/javascript';
            s.async = true;
            s.src = 'https://cdn.channel.io/plugin/ch-plugin-web.js';
            s.charset = 'UTF-8';
            var x = document.getElementsByTagName('script')[0];
            x.parentNode.insertBefore(s, x);
        }
        if (document.readyState === 'complete') {
            l();
        } else if (window.attachEvent) {
            window.attachEvent('onload', l);
        } else {
            window.addEventListener('DOMContentLoaded', l, false);
            window.addEventListener('load', l, false);
        }
    })();
});
