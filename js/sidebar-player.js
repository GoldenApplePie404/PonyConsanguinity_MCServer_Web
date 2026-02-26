// Sidebar 音乐播放器 - 本地音乐版本

// 音乐列表
const playlist = [
    {
        title: "Flying to the Hope",
        url: "/assets/music/Flying to the Hope.mp3",
        format: "MP3"
    },
    {
        title: "DreamCity",
        url: "/assets/music/DreamCity.mp3",
        format: "MP3"
    },
    {
        title: "HappyLand",
        url: "/assets/music/HappyLand.mp3",
        format: "MP3"
    }
];

// 播放器状态
let currentIndex = 0;
let isPlaying = false;
let audio = null;

// 初始化播放器
function initSidebarPlayer() {
    // 等待DOM完全准备好
    setTimeout(() => {
        // 检查关键元素是否存在
        const titleEl = document.getElementById('player-title');
        const statusEl = document.getElementById('player-status');
        const progressBar = document.querySelector('.progress-bar');

        if (!titleEl || !statusEl || !progressBar) {
            console.error('关键元素缺失，延迟重试...');
            // 延迟重试（静默重试）
            setTimeout(() => initSidebarPlayer(), 100);
            return;
        }

        // 创建音频对象
        audio = new Audio();
        audio.volume = 0.7;

        // 加载第一首歌
        loadSong(0);

        // 音频事件监听
        audio.addEventListener('timeupdate', updateProgress);
        audio.addEventListener('loadedmetadata', updateDuration);
        audio.addEventListener('ended', handleSongEnd);
        audio.addEventListener('error', handleError);

        // 进度条点击和拖动事件
        setupProgressBarEvents(progressBar);
    }, 100);
}

// 设置进度条事件
function setupProgressBarEvents(progressBar) {
    let isDragging = false;
    let wasPlaying = false;
    let hasMoved = false; // 记录是否发生了拖动（移动）
    let startX = 0;
    let targetTime = 0; // 记录目标时间
    let clickHandled = false; // 记录点击事件是否已经处理

    // 鼠标按下开始拖动
    progressBar.addEventListener('mousedown', (e) => {
        if (!audio || !audio.duration) return;
        e.preventDefault(); // 防止选中文字
        e.stopPropagation(); // 阻止事件冒泡
        isDragging = true;
        hasMoved = false;
        clickHandled = false;
        startX = e.clientX;
        wasPlaying = isPlaying;

        // 暂停过渡效果以便实时更新
        const progressEl = document.getElementById('player-progress');
        if (progressEl) {
            progressEl.style.transition = 'none';
        }

        // 计算目标时间（但不立即设置）
        const rect = progressBar.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const percentage = Math.max(0, Math.min(1, clickX / rect.width));
        targetTime = percentage * audio.duration;

        // 只更新进度条显示，不设置audio.currentTime
        updateProgressDisplay(percentage);
    });

    // 鼠标移动（拖动中）
    document.addEventListener('mousemove', (e) => {
        if (!isDragging || !audio || !audio.duration) return;

        // 记录移动距离
        const moveDistance = Math.abs(e.clientX - startX);
        if (moveDistance > 3) { // 移动超过3像素才算拖动
            hasMoved = true;
            clickHandled = true; // 拖动时标记点击已处理
        }

        const rect = progressBar.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const percentage = Math.max(0, Math.min(1, clickX / rect.width));
        targetTime = percentage * audio.duration;

        // 只更新进度条显示，不设置audio.currentTime
        updateProgressDisplay(percentage);
    });

    // 鼠标松开结束拖动
    document.addEventListener('mouseup', (e) => {
        if (isDragging) {
            const didMove = hasMoved;
            isDragging = false;

            // 恢复过渡效果
            const progressEl = document.getElementById('player-progress');
            if (progressEl) {
                progressEl.style.transition = 'width 0.1s ease';
            }

            // 只有在真正发生了拖动时才设置audio.currentTime
            if (didMove) {
                audio.currentTime = targetTime;
                clickHandled = true; // 拖动结束时标记点击已处理
            }

            // 清理标志
            setTimeout(() => {
                hasMoved = false;
                clickHandled = false;
            }, 10);
        }
    });

    // 点击进度条跳转（单独处理，不与拖动冲突）
    progressBar.addEventListener('click', (e) => {
        // 如果已经处理过（拖动），不处理点击事件
        if (clickHandled || isDragging) return;

        if (!audio || !audio.duration) return;
        e.preventDefault(); // 防止默认行为
        e.stopPropagation(); // 阻止事件冒泡

        const rect = progressBar.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const percentage = Math.max(0, Math.min(1, clickX / rect.width));
        const newTime = percentage * audio.duration;

        // 直接设置音频时间
        audio.currentTime = newTime;
        clickHandled = true; // 标记点击已处理
    });

    // 触摸事件支持（移动端）
    progressBar.addEventListener('touchstart', (e) => {
        if (!audio || !audio.duration) return;
        e.preventDefault();

        isDragging = true;
        hasMoved = false;
        clickHandled = false;
        startX = e.touches[0].clientX;
        wasPlaying = isPlaying;

        const progressEl = document.getElementById('player-progress');
        if (progressEl) {
            progressEl.style.transition = 'none';
        }

        const touch = e.touches[0];
        const rect = progressBar.getBoundingClientRect();
        const touchX = touch.clientX - rect.left;
        const percentage = Math.max(0, Math.min(1, touchX / rect.width));
        targetTime = percentage * audio.duration;

        // 只更新进度条显示，不设置audio.currentTime
        updateProgressDisplay(percentage);
    });

    document.addEventListener('touchmove', (e) => {
        if (!isDragging || !audio || !audio.duration) return;

        const touch = e.touches[0];
        const moveDistance = Math.abs(touch.clientX - startX);
        if (moveDistance > 3) {
            hasMoved = true;
            clickHandled = true; // 拖动时标记点击已处理
        }

        const rect = progressBar.getBoundingClientRect();
        const touchX = touch.clientX - rect.left;
        const percentage = Math.max(0, Math.min(1, touchX / rect.width));
        targetTime = percentage * audio.duration;

        // 只更新进度条显示，不设置audio.currentTime
        updateProgressDisplay(percentage);
    });

    document.addEventListener('touchend', (e) => {
        if (isDragging) {
            const didMove = hasMoved;
            isDragging = false;

            const progressEl = document.getElementById('player-progress');
            if (progressEl) {
                progressEl.style.transition = 'width 0.1s ease';
            }

            // 只有在真正发生了拖动时才设置audio.currentTime
            if (didMove) {
                audio.currentTime = targetTime;
                clickHandled = true; // 拖动结束时标记点击已处理
            }

            // 清理标志
            setTimeout(() => {
                hasMoved = false;
                clickHandled = false;
            }, 10);
        }
    });
}

// 更新进度条显示（不设置audio.currentTime）
function updateProgressDisplay(percentage) {
    const progressEl = document.getElementById('player-progress');
    const currentTimeEl = document.getElementById('current-time');

    if (progressEl) {
        progressEl.style.width = (percentage * 100) + '%';
    }
    if (currentTimeEl && audio && audio.duration) {
        const displayTime = percentage * audio.duration;
        currentTimeEl.textContent = formatTime(displayTime);
    }
}

// 加载歌曲
function loadSong(index) {
    if (index < 0 || index >= playlist.length) return;

    currentIndex = index;
    const song = playlist[index];

    audio.src = song.url;
    audio.load();

    // 更新UI - 检查元素是否存在
    const titleEl = document.getElementById('player-title');
    const statusEl = document.getElementById('player-status');

    if (titleEl) {
        titleEl.textContent = song.title;
    }
    if (statusEl) {
        statusEl.textContent = '准备就绪';
    }

    // 更新播放列表高亮
    updatePlaylistUI();
}

// 播放/暂停
function togglePlay() {
    if (!audio) return;

    if (isPlaying) {
        audio.pause();
        isPlaying = false;
        const statusEl = document.getElementById('player-status');
        if (statusEl) statusEl.textContent = '已暂停';
        updatePlayButton(false);
    } else {
        audio.play().then(() => {
            isPlaying = true;
            const statusEl = document.getElementById('player-status');
            if (statusEl) statusEl.textContent = '正在播放';
            updatePlayButton(true);
        }).catch(error => {
            console.error('播放失败:', error);
            const statusEl = document.getElementById('player-status');
            if (statusEl) statusEl.textContent = '播放失败';
        });
    }
}

// 更新播放按钮
function updatePlayButton(playing) {
    const playIcon = document.getElementById('play-icon');
    const pauseIcon = document.getElementById('pause-icon');

    if (playIcon && pauseIcon) {
        if (playing) {
            playIcon.style.display = 'none';
            pauseIcon.style.display = 'block';
        } else {
            playIcon.style.display = 'block';
            pauseIcon.style.display = 'none';
        }
    }
}

// 切换歌曲
function playSong(index) {
    if (!audio) return;

    // 停止当前播放
    if (isPlaying) {
        audio.pause();
    }

    // 加载并播放新歌曲
    loadSong(index);

    // 自动播放
    audio.play().then(() => {
        isPlaying = true;
        const statusEl = document.getElementById('player-status');
        if (statusEl) statusEl.textContent = '正在播放';
        updatePlayButton(true);
    }).catch(error => {
        console.error('播放失败:', error);
        const statusEl = document.getElementById('player-status');
        if (statusEl) statusEl.textContent = '播放失败';
        isPlaying = false;
        updatePlayButton(false);
    });
}

// 上一首
function prevSong() {
    let newIndex = currentIndex - 1;
    if (newIndex < 0) {
        newIndex = playlist.length - 1;
    }
    playSong(newIndex);
}

// 下一首
function nextSong() {
    let newIndex = currentIndex + 1;
    if (newIndex >= playlist.length) {
        newIndex = 0;
    }
    playSong(newIndex);
}

// 更新进度
function updateProgress() {
    if (!audio || !audio.duration) return;

    const progress = (audio.currentTime / audio.duration) * 100;
    const progressEl = document.getElementById('player-progress');
    const currentTimeEl = document.getElementById('current-time');

    if (progressEl) {
        progressEl.style.width = progress + '%';
    }
    if (currentTimeEl) {
        currentTimeEl.textContent = formatTime(audio.currentTime);
    }
}

// 更新总时长
function updateDuration() {
    if (!audio) return;

    const durationEl = document.getElementById('duration');
    if (durationEl) {
        durationEl.textContent = formatTime(audio.duration);
    }
}

// 格式化时间
function formatTime(seconds) {
    if (isNaN(seconds)) return '0:00';

    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

// 改变音量
function changeVolume(value) {
    if (!audio) return;

    audio.volume = value;
}

// 歌曲结束处理
function handleSongEnd() {
    // 自动播放下一首
    nextSong();
}

// 错误处理
function handleError(error) {
    console.error('音频错误:', error);
    const statusEl = document.getElementById('player-status');
    if (statusEl) statusEl.textContent = '加载失败';

    // 尝试播放下一首
    nextSong();
}

// 更新播放列表UI
function updatePlaylistUI() {
    const items = document.querySelectorAll('.playlist-item');
    items.forEach((item, index) => {
        if (index === currentIndex) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
}

// 切换播放器面板显示
function togglePlayer() {
    const panel = document.querySelector('.player-panel');
    const toggle = document.querySelector('.player-toggle');
    if (panel) {
        const isShowing = panel.classList.contains('show');
        panel.classList.toggle('show');
        
        // 切换按钮的active类
        if (toggle) {
            toggle.classList.toggle('active', !isShowing);
        }
    }
}

// 页面加载完成后初始化
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebarPlayer);
} else {
    initSidebarPlayer();
}
