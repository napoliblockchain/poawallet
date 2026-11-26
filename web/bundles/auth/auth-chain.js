/*
 * Pagine di accesso Napay: catena di blocchi animata dietro al marchio (stesso file nelle tre app).
 * Corsie di blocchi collegati da maglie che scorrono verso destra; ogni tanto un blocco "validato" si accende.
 * Con movimento ridotto richiesto dal sistema disegna un solo fotogramma fermo.
 */
(function () {
    'use strict';

    function ChainAnimation(canvas, host) {
        var ctx = canvas.getContext('2d');
        var light = (getComputedStyle(host).getPropertyValue('--auth-light') || '#ffffff').trim();
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var lanes = [], W = 0, H = 0, raf = 0, last = 0, nextPulse = 0, hex = '0123456789abcdef';

        function hash() { var s = '0x'; for (var i = 0; i < 4; i++) s += hex[Math.floor(Math.random() * 16)]; return s + '…'; }
        function rgba(color, a) {
            var m = /^#([0-9a-f]{6})$/i.exec(color), n = m ? parseInt(m[1], 16) : 0xffffff;
            return 'rgba(' + (n >> 16 & 255) + ',' + (n >> 8 & 255) + ',' + (n & 255) + ',' + a + ')';
        }
        function setup() {
            var dpr = Math.min(window.devicePixelRatio || 1, 2), height = 1000 + Math.floor(Math.random() * 9000);
            W = host.clientWidth; H = host.clientHeight;
            canvas.width = Math.round(W * dpr); canvas.height = Math.round(H * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            lanes = [];
            var count = Math.max(2, Math.min(4, Math.floor(H / 90)));
            for (var i = 0; i < count; i++) {
                var depth = 0.55 + 0.45 * ((i * 7) % count) / Math.max(1, count - 1);
                var size = 46 * depth, gap = size * 1.25;
                var lane = { y: H * (i + 0.5) / count, size: size, gap: gap, speed: (0.18 + 0.22 * depth) * (i % 2 ? 1 : 0.8), alpha: 0.18 + 0.5 * depth, blocks: [] };
                var x = -Math.random() * (size + gap), top = height + Math.ceil((W + size - x) / (size + gap));
                while (x < W + size) { lane.blocks.push({ x: x, n: top--, h: hash(), glow: 0 }); x += size + gap; }
                lanes.push(lane);
            }
        }
        function roundRect(x, y, w, h, r) {
            ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
            ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
        }
        function link(x1, x2, y, a) {
            var len = x2 - x1, r = Math.min(7, len / 5);
            ctx.strokeStyle = 'rgba(255,255,255,' + (a * 0.55) + ')'; ctx.lineWidth = 1.2;
            ctx.beginPath(); ctx.moveTo(x1, y); ctx.lineTo(x1 + len * 0.22, y); ctx.moveTo(x2 - len * 0.22, y); ctx.lineTo(x2, y); ctx.stroke();
            ctx.beginPath(); ctx.ellipse(x1 + len * 0.42, y, r * 1.3, r * 0.65, 0, 0, Math.PI * 2); ctx.stroke();
            ctx.beginPath(); ctx.ellipse(x1 + len * 0.58, y, r * 1.3, r * 0.65, 0, 0, Math.PI * 2); ctx.stroke();
        }
        function block(b, lane) {
            var s = lane.size, x = b.x, y = lane.y - s / 2, a = lane.alpha;
            if (b.glow > 0) {
                var g = ctx.createRadialGradient(x + s / 2, lane.y, 0, x + s / 2, lane.y, s * 1.6);
                g.addColorStop(0, rgba(light, 0.55 * b.glow)); g.addColorStop(1, rgba(light, 0));
                ctx.fillStyle = g; ctx.fillRect(x - s, lane.y - s * 1.6, s * 3, s * 3.2);
            }
            roundRect(x, y, s, s, s * 0.18);
            ctx.fillStyle = 'rgba(255,255,255,' + (a * 0.12 + b.glow * 0.25) + ')'; ctx.fill();
            ctx.strokeStyle = b.glow > 0 ? rgba(light, 0.5 + 0.5 * b.glow) : 'rgba(255,255,255,' + (a * 0.7) + ')';
            ctx.lineWidth = 1.3; ctx.stroke();
            ctx.strokeStyle = 'rgba(255,255,255,' + (a * 0.35) + ')'; ctx.lineWidth = 1;
            for (var k = 0; k < 2; k++) {
                ctx.beginPath(); ctx.moveTo(x + s * 0.2, y + s * (0.62 + k * 0.14)); ctx.lineTo(x + s * 0.8, y + s * (0.62 + k * 0.14)); ctx.stroke();
            }
            if (s > 34) {
                ctx.textAlign = 'center';
                ctx.fillStyle = 'rgba(255,255,255,' + Math.min(1, a + 0.15) + ')';
                ctx.font = '600 ' + Math.round(s * 0.2) + 'px ui-monospace, Menlo, Consolas, monospace';
                ctx.fillText('#' + b.n, x + s / 2, y + s * 0.34);
                ctx.fillStyle = 'rgba(255,255,255,' + (a * 0.7) + ')';
                ctx.font = Math.round(s * 0.16) + 'px ui-monospace, Menlo, Consolas, monospace';
                ctx.fillText(b.h, x + s / 2, y + s * 0.52);
            }
        }
        function frame(t) {
            var dt = last ? Math.min(50, t - last) / 16.7 : 1;
            last = t;
            ctx.clearRect(0, 0, W, H);
            if (!reduce && t > nextPulse && lanes.length) {
                var lane = lanes[Math.floor(Math.random() * lanes.length)];
                var visible = lane.blocks.filter(function (b) { return b.x > W * 0.15 && b.x < W * 0.8; });
                if (visible.length) visible[Math.floor(Math.random() * visible.length)].glow = 1;
                nextPulse = t + 1400 + Math.random() * 1600;
            }
            lanes.forEach(function (lane) {
                lane.blocks.forEach(function (b) { if (!reduce) b.x += lane.speed * dt; b.glow = Math.max(0, b.glow - 0.012 * dt); });
                var first = lane.blocks[0];
                if (first.x > -lane.gap) lane.blocks.unshift({ x: first.x - lane.size - lane.gap, n: first.n + 1, h: hash(), glow: 0 });
                while (lane.blocks.length > 1 && lane.blocks[lane.blocks.length - 1].x > W + lane.size) lane.blocks.pop();
                for (var i = 0; i < lane.blocks.length - 1; i++) link(lane.blocks[i].x + lane.size, lane.blocks[i + 1].x, lane.y, lane.alpha);
                lane.blocks.forEach(function (b) { block(b, lane); });
            });
            if (!reduce && !document.hidden) raf = requestAnimationFrame(frame);
        }
        function restart() { cancelAnimationFrame(raf); last = 0; setup(); raf = requestAnimationFrame(frame); }

        restart();
        if (window.ResizeObserver) new ResizeObserver(restart).observe(host);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) { cancelAnimationFrame(raf); last = 0; raf = requestAnimationFrame(frame); } });
    }

    function init() {
        document.querySelectorAll('canvas.auth-chain').forEach(function (canvas) {
            if (canvas.getContext && canvas.parentElement) new ChainAnimation(canvas, canvas.parentElement);
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
