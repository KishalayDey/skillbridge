/* ==========================================
   Messages Page - AJAX
   Every 5 seconds we ask get_messages.php for the messages
   of this chat, so a new message appears without pressing F5.
   ========================================== */

function loadMessages() {

    let xhr = new XMLHttpRequest();

    xhr.open("GET", "get_messages.php?to=" + chatWith, true);

    xhr.onreadystatechange = function () {

        if (xhr.readyState == 4 && xhr.status == 200) {

            // the PHP file sends the messages as JSON
            let messages = JSON.parse(xhr.responseText);

            let html = "";

            if (messages.length == 0) {
                html = "<div class='empty'>No message yet. Write the first message.</div>";
            }

            for (let i = 0; i < messages.length; i++) {

                if (messages[i].from_id == myId) {
                    html = html + "<div class='msg-out'>" + messages[i].text;
                    html = html + "<span class='msg-time'>" + messages[i].time + "</span></div>";
                }
                else {
                    html = html + "<div class='msg-in'>" + messages[i].text;
                    html = html + "<span class='msg-time'>" + messages[i].time + "</span></div>";
                }

            }

            let box = document.getElementById("chatBox");

            box.innerHTML = html;
            box.scrollTop = box.scrollHeight;      // always show the last message

        }

    };

    xhr.send();

}

// show the last message when the page opens
let chatBox = document.getElementById("chatBox");
chatBox.scrollTop = chatBox.scrollHeight;

// then check for new messages every 5 seconds
setInterval(loadMessages, 5000);
