/* 陪玩点单系统 - 前台交互 */
(function () {
  'use strict';

  // 数字格式化
  function fmt(n) {
    return (Math.round(n * 100) / 100).toFixed(2);
  }

  // ---------------------------------------------------------- 下单数量与总价
  document.querySelectorAll('.order-form').forEach(function (form) {
    var hidden = form.querySelector('input[name="quantity"]');
    var label = form.querySelector('.qty-value');
    var totalEl = form.querySelector('.total-price');
    if (!hidden || !label) return;

    var price = parseFloat(label.dataset.price || '0');
    var unit = label.dataset.unit || '';
    var qty = 1;

    function render() {
      label.textContent = qty + ' ' + unit;
      hidden.value = qty;
      if (totalEl) totalEl.textContent = '¥' + fmt(price * qty);
    }

    form.querySelectorAll('.qty-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var step = parseInt(btn.dataset.step, 10) || 0;
        qty = Math.min(99, Math.max(1, qty + step));
        render();
      });
    });

    render();
  });

  // ---------------------------------------------------------- 表单二次确认
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.dataset.confirm)) {
        e.preventDefault();
      }
    });
  });
  document.querySelectorAll('a[data-confirm]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      if (!window.confirm(link.dataset.confirm)) e.preventDefault();
    });
  });
  document.querySelectorAll('button[data-confirm]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      if (!window.confirm(btn.dataset.confirm)) e.preventDefault();
    });
  });

  // ---------------------------------------------------------- 充值金额快捷选择
  var rechargeInput = document.getElementById('recharge-amount');
  document.querySelectorAll('.amount-chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      if (!rechargeInput) return;
      rechargeInput.value = chip.dataset.amount;
      document.querySelectorAll('.amount-chip').forEach(function (c) { c.classList.remove('active'); });
      chip.classList.add('active');
    });
  });

  // ---------------------------------------------------------- 提现到账金额试算
  var withdrawInput = document.getElementById('withdraw-amount');
  var withdrawReal = document.getElementById('withdraw-real');
  if (withdrawInput && withdrawReal) {
    var feeRate = parseFloat(withdrawInput.dataset.feeRate || '0');
    var calc = function () {
      var amount = parseFloat(withdrawInput.value || '0');
      if (!amount || amount <= 0) {
        withdrawReal.textContent = '-';
        return;
      }
      var fee = amount * feeRate / 100;
      withdrawReal.textContent = fmt(amount - fee);
    };
    withdrawInput.addEventListener('input', calc);
    calc();
  }

  // ---------------------------------------------------------- 星级评分
  document.querySelectorAll('.star-picker').forEach(function (picker) {
    var input = picker.querySelector('input[type="hidden"]');
    var stars = picker.querySelectorAll('.star');
    var setValue = function (v) {
      if (input) input.value = v;
      stars.forEach(function (s) {
        s.classList.toggle('active', parseInt(s.dataset.value, 10) <= v);
      });
    };
    stars.forEach(function (star) {
      star.addEventListener('click', function () {
        setValue(parseInt(star.dataset.value, 10) || 5);
      });
    });
    setValue(parseInt(input && input.value ? input.value : '5', 10));
  });

  // ---------------------------------------------------------- 头像预览
  var avatarInput = document.getElementById('avatar-input');
  var avatarPreview = document.getElementById('avatar-preview');
  if (avatarInput && avatarPreview) {
    avatarInput.addEventListener('change', function () {
      var file = avatarInput.files && avatarInput.files[0];
      if (!file) return;
      avatarPreview.src = URL.createObjectURL(file);
    });
  }

  // ---------------------------------------------------------- 发送邮箱验证码
  document.querySelectorAll('[data-send-code]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = document.querySelector(btn.dataset.target || btn.dataset.emailTarget || '');
      var email = target ? String(target.value || '').trim() : '';
      if (!email) {
        window.alert('请先填写' + (btn.dataset.targetLabel || '邮箱/手机号'));
        if (target) target.focus();
        return;
      }

      var original = btn.textContent;
      btn.disabled = true;
      btn.textContent = '发送中…';

      var data = new FormData();
      data.append('_token', btn.dataset.token || '');
      data.append('target', email);
      data.append('email', email);
      data.append('scene', btn.dataset.scene || 'register');

      fetch(btn.dataset.url, {
        method: 'POST',
        body: data,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (res) { return res.json(); })
        .then(function (res) {
          window.alert(res.msg || (res.code === 0 ? '验证码已发送' : '发送失败'));
          if (res.code === 0) {
            var left = 60;
            btn.textContent = left + ' 秒后可重发';
            var timer = setInterval(function () {
              left--;
              if (left <= 0) {
                clearInterval(timer);
                btn.disabled = false;
                btn.textContent = original;
              } else {
                btn.textContent = left + ' 秒后可重发';
              }
            }, 1000);
          } else {
            btn.disabled = false;
            btn.textContent = original;
          }
        })
        .catch(function () {
          window.alert('网络错误，请稍后重试');
          btn.disabled = false;
          btn.textContent = original;
        });
    });
  });

  // ---------------------------------------------------------- 陪玩瀑布流无限加载
  var sentinel = document.getElementById('wf-sentinel');
  if (sentinel) {
    var wfPage = parseInt(sentinel.dataset.page || '1', 10);
    var wfList = document.getElementById('wf-list');
    var wfLoading = false;

    var loadMore = function () {
      if (wfLoading || sentinel.dataset.hasMore === '0') return;
      wfLoading = true;
      sentinel.textContent = '加载中…';

      fetch(sentinel.dataset.url + '&page=' + (wfPage + 1), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res && res.code === 0 && res.data) {
            if (wfList && res.data.html) {
              wfList.insertAdjacentHTML('beforeend', res.data.html);
            }
            wfPage = res.data.page;
            sentinel.dataset.page = wfPage;
            if (res.data.has_more) {
              sentinel.dataset.hasMore = '1';
              sentinel.textContent = '上滑加载更多…';
            } else {
              sentinel.dataset.hasMore = '0';
              sentinel.textContent = '没有更多了';
              if (observer) observer.disconnect();
            }
          } else {
            sentinel.textContent = '加载失败，点击重试';
          }
          wfLoading = false;
        })
        .catch(function () {
          sentinel.textContent = '加载失败，点击重试';
          wfLoading = false;
        });
    };

    var observer = null;
    if ('IntersectionObserver' in window) {
      observer = new IntersectionObserver(function (entries) {
        if (entries[0].isIntersecting) loadMore();
      }, { rootMargin: '240px' });
      observer.observe(sentinel);
    }
    // 兜底：不支持 IntersectionObserver 或想手动加载时点击即可
    sentinel.addEventListener('click', loadMore);
  }

  // ---------------------------------------------------------- 工单卡片（在线咨询）
  var ticketMask = document.getElementById('ticket-mask');
  var ticketForm = document.getElementById('ticket-form');
  var ticketOrderId = document.getElementById('ticket-order-id');

  function closeTicket() {
    if (!ticketMask) return;
    ticketMask.classList.remove('open');
    ticketMask.hidden = true;
    document.body.style.overflow = '';
  }
  function openTicket(orderId) {
    if (!ticketMask) return;
    if (orderId && ticketOrderId) ticketOrderId.value = orderId;
    ticketMask.hidden = false;
    ticketMask.classList.add('open');
    document.body.style.overflow = 'hidden';
    var first = ticketMask.querySelector('input[name="title"]');
    if (first) setTimeout(function () { first.focus(); }, 60);
  }

  // 悬浮按钮本身是指向「我的工单」的链接：没有 JS 时正常跳转，有 JS 时改成打开卡片
  var ticketFab = document.getElementById('ticket-fab');
  if (ticketFab) {
    ticketFab.addEventListener('click', function (e) {
      if (!ticketMask) return;
      e.preventDefault();
      openTicket('');
    });
  }
  document.querySelectorAll('[data-ticket-open]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      openTicket(el.dataset.orderId || '');
    });
  });
  var ticketClose = document.getElementById('ticket-close');
  if (ticketClose) ticketClose.addEventListener('click', closeTicket);
  if (ticketMask) {
    ticketMask.addEventListener('click', function (e) {
      if (e.target === ticketMask) closeTicket();
    });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeTicket();
  });

  // 类型说明随选择变化
  if (ticketMask) {
    var hint = document.getElementById('ticket-cat-hint');
    ticketMask.querySelectorAll('input[name="category"]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        if (hint && radio.dataset.hint) hint.textContent = radio.dataset.hint;
      });
    });
  }

  // AJAX 提交，成功后跳到工单详情
  if (ticketForm) {
    ticketForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = ticketForm.querySelector('button[type="submit"]');
      var origin = btn ? btn.textContent : '';
      if (btn) { btn.disabled = true; btn.textContent = '提交中…'; }

      fetch(ticketForm.action, {
        method: 'POST',
        body: new FormData(ticketForm),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          window.alert((res && res.msg) || '已提交');
          if (res && res.code === 0) {
            closeTicket();
            ticketForm.reset();
            if (res.data && res.data.url) { window.location.href = res.data.url; }
          } else if (btn) {
            btn.disabled = false;
            btn.textContent = origin;
          }
        })
        .catch(function () {
          window.alert('网络错误，请稍后重试');
          if (btn) { btn.disabled = false; btn.textContent = origin; }
        });
    });
  }

  // ---------------------------------------------------------- 公告走马灯（上下滚动）
  var noticeTrack = document.getElementById('notice-track');
  if (noticeTrack && noticeTrack.children.length > 1) {
    var noticeViewport = document.getElementById('notice-viewport');
    var noticeIndex = 0;
    var noticePaused = false;
    var noticeItemH = noticeTrack.children[0].offsetHeight || 20;
    // 滚动间隔（秒）由后台「运营展示」设置，data-interval 没读到就用 4 秒
    var noticeSec = noticeViewport ? parseInt(noticeViewport.dataset.interval || '4', 10) : 4;
    var noticeInterval = (noticeSec >= 2 && noticeSec <= 60 ? noticeSec : 4) * 1000;

    if (noticeViewport) {
      noticeViewport.addEventListener('mouseenter', function () { noticePaused = true; });
      noticeViewport.addEventListener('mouseleave', function () { noticePaused = false; });
    }

    setInterval(function () {
      if (noticePaused || !noticeTrack.children.length) return;
      noticeIndex = (noticeIndex + 1) % noticeTrack.children.length;
      noticeTrack.style.transform = 'translateY(-' + (noticeIndex * noticeItemH) + 'px)';
    }, noticeInterval);
  }

  // ---------------------------------------------------------- 首页广告轮播
  var bannerCarousel = document.getElementById('banner-carousel');
  var bannerTrack = document.getElementById('banner-track');
  if (bannerCarousel && bannerTrack && bannerTrack.children.length > 1) {
    var bannerDots = document.getElementById('banner-dots');
    var bannerIndex = 0;
    var bannerTimer = null;
    var bannerCount = bannerTrack.children.length;
    // 轮播间隔（秒）由后台「运营展示」设置
    var bannerSec = parseInt(bannerCarousel.dataset.interval || '5', 10);
    var bannerInterval = (bannerSec >= 2 && bannerSec <= 60 ? bannerSec : 5) * 1000;

    var bannerGo = function (idx) {
      bannerIndex = (idx + bannerCount) % bannerCount;
      var width = bannerCarousel.clientWidth || 0;
      bannerTrack.style.transform = 'translateX(-' + (bannerIndex * width) + 'px)';
      if (bannerDots) {
        for (var i = 0; i < bannerDots.children.length; i++) {
          bannerDots.children[i].className = 'banner-dot' + (i === bannerIndex ? ' active' : '');
        }
      }
    };
    var bannerStop = function () {
      if (bannerTimer) clearInterval(bannerTimer);
      bannerTimer = null;
    };
    var bannerPlay = function () {
      bannerStop();
      bannerTimer = setInterval(function () { bannerGo(bannerIndex + 1); }, bannerInterval);
    };

    if (bannerDots) {
      for (var bi = 0; bi < bannerCount; bi++) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'banner-dot' + (bi === 0 ? ' active' : '');
        dot.setAttribute('data-index', String(bi));
        dot.setAttribute('aria-label', '第 ' + (bi + 1) + ' 张');
        bannerDots.appendChild(dot);
      }
      bannerDots.addEventListener('click', function (e) {
        var t = e.target;
        if (t && typeof t.className === 'string' && t.className.indexOf('banner-dot') === 0) {
          bannerGo(parseInt(t.getAttribute('data-index'), 10) || 0);
          bannerPlay();
        }
      });
    }

    bannerCarousel.addEventListener('mouseenter', bannerStop);
    bannerCarousel.addEventListener('mouseleave', bannerPlay);

    var bannerStartX = 0;
    bannerCarousel.addEventListener('touchstart', function (e) {
      bannerStartX = e.touches[0].clientX;
      bannerStop();
    }, { passive: true });
    bannerCarousel.addEventListener('touchend', function (e) {
      var dx = e.changedTouches[0].clientX - bannerStartX;
      if (Math.abs(dx) > 40) { bannerGo(bannerIndex + (dx < 0 ? 1 : -1)); }
      bannerPlay();
    });

    window.addEventListener('resize', function () { bannerGo(bannerIndex); });
    bannerPlay();
  }

  // ---------------------------------------------------------- 后台重置密码：随机生成
  var resetPassBtn = document.getElementById('reset-pass-random');
  if (resetPassBtn) {
    resetPassBtn.addEventListener('click', function () {
      var chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
      var pass = '';
      var i;
      if (window.crypto && window.crypto.getRandomValues) {
        var buf = new Uint32Array(10);
        window.crypto.getRandomValues(buf);
        for (i = 0; i < 10; i++) { pass += chars.charAt(buf[i] % chars.length); }
      } else {
        for (i = 0; i < 10; i++) { pass += chars.charAt(Math.floor(Math.random() * chars.length)); }
      }
      var input = document.getElementById('reset-pass');
      if (input) {
        input.value = pass;
        input.type = 'text';
      }
      window.alert('已生成随机密码：' + pass + '\n请复制后告知用户（关掉页面就看不到了）');
    });
  }

  // ---------------------------------------------------------- 订单聊天（发送 + 轮询）
  var chatList = document.getElementById('chat-list');
  var chatForm = document.getElementById('chat-form');
  if (chatList && chatForm) {
    var chatOrderId = chatForm.dataset.orderId || '0';
    var chatPollUrl = chatForm.dataset.poll || '';
    var chatCount = parseInt(chatForm.dataset.count || '0', 10);
    var chatInput = chatForm.querySelector('textarea[name="content"]');

    var chatScroll = function () { chatList.scrollTop = chatList.scrollHeight; };
    chatScroll();

    var chatApply = function (data) {
      if (!data) return;
      if (typeof data.html === 'string') { chatList.innerHTML = data.html; }
      if (typeof data.count === 'number') { chatCount = data.count; }
      chatScroll();
    };

    var chatSubmit = function () {
      if (!chatInput || !chatInput.value.trim()) {
        window.alert('请输入消息内容');
        return;
      }
      var btn = chatForm.querySelector('button[type="submit"]');
      if (btn) btn.disabled = true;

      fetch(chatForm.action, {
        method: 'POST',
        body: new FormData(chatForm),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (btn) btn.disabled = false;
          if (!res || res.code !== 0) {
            window.alert((res && res.msg) || '发送失败');
            return;
          }
          chatInput.value = '';
          chatApply(res.data);
        })
        .catch(function () {
          if (btn) btn.disabled = false;
          window.alert('网络错误，请稍后重试');
        });
    };

    chatForm.addEventListener('submit', function (e) {
      e.preventDefault();
      chatSubmit();
    });

    // Enter 发送，Shift+Enter 换行
    if (chatInput) {
      chatInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          chatSubmit();
        }
      });
    }

    // 每 8 秒拉一次新消息（有变化才重绘，避免打断输入）
    if (chatPollUrl) {
      setInterval(function () {
        fetch(chatPollUrl + '&id=' + chatOrderId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (res && res.code === 0 && res.data && res.data.count !== chatCount) {
              chatApply(res.data);
            }
          })
          .catch(function () { /* 轮询失败静默 */ });
      }, 8000);
    }
  }

  // ---------------------------------------------------------- flash 提示自动淡出
  // 只淡出 flash 消息（.alert-flash），常驻提示（如"审核中"）保持显示
  document.querySelectorAll('.alert-flash').forEach(function (alert) {
    setTimeout(function () {
      alert.style.transition = 'opacity .4s';
      alert.style.opacity = '0';
      setTimeout(function () { alert.style.display = 'none'; }, 420);
    }, 4200);
  });
  // ---------------------------------------------------------- 点击复制
  // 用法：<button data-copy="#some-id">复制</button>
  //   或：<button data-copy-text="要复制的文本">复制</button>
  //
  // 为什么不用 navigator.clipboard：它要求安全上下文（https / localhost），
  // 本站是 http + IP，那个 API 在浏览器里直接是 undefined，只能用 execCommand 兜底。
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.top = '-1000px';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      ta.setSelectionRange(0, ta.value.length);
      var ok = false;
      try {
        ok = document.execCommand('copy');
      } catch (e) {
        ok = false;
      }
      document.body.removeChild(ta);
      if (ok) { resolve(); } else { reject(); }
    });
  }

  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var sel = btn.getAttribute('data-copy');
      var el = sel ? document.querySelector(sel) : null;
      var text = btn.getAttribute('data-copy-text') || (el ? (el.textContent || '').trim() : '');
      if (!text) { return; }

      var old = btn.textContent;
      copyText(text).then(function () {
        btn.textContent = '已复制';
        setTimeout(function () { btn.textContent = old; }, 1500);
      }).catch(function () {
        btn.textContent = '复制失败';
        setTimeout(function () { btn.textContent = old; }, 1500);
      });
    });
  });

  // ---------------------------------------------------------- 考核标准倒计时
  // 用法：<div data-assess-gate="#某按钮" data-assess-seconds="4"></div>
  // 目标按钮在倒计时结束前保持禁用 —— 需求是「必须看满 4 秒才能提交申请」，
  // 所以这里要真的拦住，而不只是提示一句。
  document.querySelectorAll('[data-assess-gate]').forEach(function (box) {
    var btn = document.querySelector(box.getAttribute('data-assess-gate'));
    if (!btn) { return; }

    var seconds = parseInt(box.getAttribute('data-assess-seconds'), 10);
    if (!(seconds > 0)) { seconds = 4; }

    var label = btn.textContent;
    var left = seconds;
    btn.disabled = true;

    var paint = function () {
      btn.textContent = left > 0
        ? label + '（请先阅读考核标准 ' + left + 's）'
        : label;
    };
    paint();

    var timer = setInterval(function () {
      left -= 1;
      if (left <= 0) {
        clearInterval(timer);
        btn.disabled = false;
        left = 0;
      }
      paint();
    }, 1000);
  });
})();
