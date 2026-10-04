/*****************************************
  *----------------------------------
  |  ThisJsVersion: 2.4.0         |
  |  © 2021-2024 By PusyuuWanko/  |
  |  LastUpdate: 2024-09-02       |
  |  License: MIT License         |
  |  SimpleMemoNote               |
----------------------------------*
******************************************/

window.addEventListener("DOMContentLoaded", function() {
  const isJs = document.getElementById("isJavascript");
  isJs.style.color = "unset";
  isJs.style.fontSize = "unset";
  isJs.innerHTML = `
    <form class="form" name="MemoForm">
      <textarea name="Memo" placeholder="メモを入力"></textarea>
      <span id="moziCount">Counter</span>
    </form>
    <div id="myModal">
      <div>
        <p>文字コードの選択とファイル名の指定</p>
        <div>
          <select id="charsetSelect">
            <option value="">キャラクターを選択</option>
            <option value="UTF-8">UTF-8</option>
            <option value="ASCII">ASCII</option>
          </select>
          <input id="fileName" placeholder="空の場合はデフォルトのファイル名が適応されます。" />
        </div>
        <div class="buttons">
          <button id="sendSaveButton">実行</button>
          <button onclick="closeSaveSettingModal()">閉じる</button>
        </div>
      </div>
    </div>
    <div class="buttons">
      <button type="button" value="保存" onclick=save(); >保存</button>
      <button type="button" value="読込" onclick=load(); >読込</button>
      <button type="button" value="削除" onclick=remove(); >削除</button>
      <button type="button" value="すべてを大文字" onclick=moziUpper(); >すべてを大文字</button>
      <button type="button" value="すべてを小文字" onclick=moziLower(); >すべてを小文字</button>
    </div>
  `;
  
  const draggableItems = document.querySelectorAll('.draggable-item');

  let draggedItem = null;

  // ドラッグ開始時の処理
  function dragStart(e) {
    draggedItem = this;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', this.innerHTML);
  }

  // ドラッグ中の処理
  function dragOver(e) {
    if (e.preventDefault) {
      e.preventDefault();
    }
    e.dataTransfer.dropEffect = 'move';
    return false;
  }

  // ドロップ時の処理
  function drop(e) {
    if (e.stopPropagation) {
      e.stopPropagation();
    }

    if (draggedItem !== this) {
      draggedItem.innerHTML = this.innerHTML;
      this.innerHTML = e.dataTransfer.getData('text/html');
      updateLocalStorageOrder();
    }

    return false;
  }

  // ドラッグ要素にイベントリスナーを追加
  draggableItems.forEach(item => {
    item.addEventListener('dragstart', dragStart);
    item.addEventListener('dragover', dragOver);
    item.addEventListener('drop', drop);
  });

  // ローカルストレージの順番を更新
  function updateLocalStorageOrder() {
    const appLinkElements = document.querySelectorAll('.draggable-item');
    const order = Array.from(appLinkElements).map(item => item.outerHTML);
    localStorage.setItem('appOrder', JSON.stringify(order));
  }

  // ローカルストレージから順番を復元
  function restoreOrderFromLocalStorage() {
    const appOrder = JSON.parse(localStorage.getItem('appOrder'));
    if (appOrder) {
      const appContainer = document.querySelector('.yokoori');
      appContainer.innerHTML = appOrder.join('');
      addDragListeners(); // ドラッグアンドドロップのリスナーを追加
    }
  }

  // ドラッグ要素にイベントリスナーを再度追加
  function addDragListeners() {
    const draggableItems = document.querySelectorAll('.draggable-item');
    draggableItems.forEach(item => {
      item.addEventListener('dragstart', dragStart);
      item.addEventListener('dragover', dragOver);
      item.addEventListener('drop', drop);
    });
  }

  // ページ読み込み時にローカルストレージから順番を復元
  restoreOrderFromLocalStorage(); // ローカルストレージからの復元を行う
});

function showSaveSettingModal() {
  document.getElementById('myModal').style.display = 'block';
}

function closeSaveSettingModal() {
  document.getElementById('myModal').style.display = 'none';
}

function save() {
  let MemoData = document.MemoForm.Memo.value;
  const sendSave = document.getElementById("sendSaveButton");
  const selectedCharset = document.getElementById('charsetSelect');
  const userFileName = document.getElementById("fileName");
  
  showSaveSettingModal();

  sendSave.addEventListener("click", function() {
    if (selectedCharset.value !== "") {
      function createSaveFile(fileName) {      
        // Convert the MemoData to the selected character set
        const encoder = new TextEncoder();
        const decodedMemoData = encoder.encode(MemoData);
        const decodedText = new TextDecoder(selectedCharset.value).decode(decodedMemoData);

        // Create a Blob with the converted text
        const blob = new Blob([new TextEncoder().encode(decodedText)], {
          type: 'text/plain;charset=' + selectedCharset.value
        });
      
        // Create a download link
        const downloadLink = document.createElement('a');
        
        downloadLink.download = fileName;
        
        downloadLink.href = URL.createObjectURL(blob);
        
        // Trigger the download
        downloadLink.click();
      }

      // Create a timestamp for the file name
      function time() {
        const now = new Date();
        const year = now.getFullYear();
        const month = now.getMonth() + 1;
        const day = now.getDate();
        const hour = now.getHours();
        const min = now.getMinutes();
        const sec = now.getSeconds();
        function addZero(number) {
          return number < 10 ? `0${number}` : number;
        }
        const formattedTime = `_${addZero(year)}${addZero(month)}${addZero(day)}_${addZero(hour)}${addZero(min)}${addZero(sec)}`;
        return formattedTime;
      }

      // Set the download link attributes
      if (userFileName && userFileName.value.length > 0) {
        if (userFileName.value.endsWith(".").length > 1/* && parts[parts.length - 1].length > 0*/) {
          createSaveFile(userFileName.value);
        } else {
          if (confirm("問題が発生しました、拡張子がついていません。\n\n.txtとして保存する場合はOKを押してください。")) {
            createSaveFile(userFileName.value + ".txt");
          } else {
            if (confirm("拡張子なしで保存しますか？\n\nOKを押さない場合は保存処理はキャンセルされます。")) {
             createSaveFile(userFileName.value);
            } else {
              userFileName.value = "";
              selectedCharset.value = "";
              closeSaveSettingModal();
              alert("保存処理をキャンセルしました。");
            }
          }
        }
      } else {
        createSaveFile('P-memo' + time() + '.txt');
      }
    } else {
      alert("キャラクターの選択は必須です。選択を行ってください。");
    }
  });
}

function loadFile(event) {
  const file = event.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = function (event) {
    const MemoData = event.target.result;
    document.MemoForm.Memo.value = MemoData;
    
    updateMoziCount();
    saveTemporarily();
  };
  reader.readAsText(file);
}

function load() {
  const fileInput = document.createElement('input');
  fileInput.type = 'file';
  fileInput.accept = 'text/plain'; // テキストファイルのみを選択可能にする
  fileInput.addEventListener('change', loadFile);
  fileInput.click();
}

function remove() {
  if (confirm("削除しますか？")) {
    document.MemoForm.Memo.value = "";
    updateMoziCount();
    alert("メモデータを削除しました。\n\n誤って削除されてしまった場合、このダイアログを閉じた後に他の機能に触らずブラウザをリロードすると復元可能です。\n\n※他の機能に触れてしまうと一時保存機能のデータが上書きされてしまい復元が行えなくなるのでご注意ください。");
  } else {
    alert("削除処理をキャンセルしました。");
  }
}

function moziUpper() {
  let text = document.MemoForm.Memo.value;
  text = text.toUpperCase();
  document.MemoForm.Memo.value = text;
}

function moziLower() {
  let text = document.MemoForm.Memo.value;
  text = text.toLowerCase();
  document.MemoForm.Memo.value = text;
}

function updateMoziCount() {
  const text = document.MemoForm.Memo;
  document.getElementById("moziCount").innerHTML = data = text.value.length + "Mozi";
}

function saveTemporarily() {
  let text = document.MemoForm.Memo.value;
  sessionStorage.setItem("Memo", text);
}

window.onload = function() {
  const text = document.MemoForm.Memo;
  text.addEventListener('input', ()=> {
    updateMoziCount();
    saveTemporarily();
  });
  updateMoziCount();
  let getMemoData = sessionStorage.getItem("Memo");
  if (getMemoData) {
    text.value = getMemoData;
    updateMoziCount();
  }
}
