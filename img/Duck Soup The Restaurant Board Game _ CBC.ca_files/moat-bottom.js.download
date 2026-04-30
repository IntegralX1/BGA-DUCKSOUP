(function() {
	function D() {
		for (var a = [64], c = 1; 3 >= c; c++) a.push(64 + c), a.push(64 - c);
		a = a[Math.floor(Math.random() * a.length)];
		return {
			b: a,
			a: 0 == Math.floor(Math.random() * a)
		}
	}

	function l(a) {
		var c = a = a.replace(":", "");
		try {
			for (var d = 0; 100 > d && (a = decodeURIComponent(a), c != a) && !a.match(/^http(s)?\:/); d++) c = a
		} catch (b) {}
		return a.replace(/(^\s+|\s+$)/g, "")
	}
	try {
		var z = function(a, c, d, b) {
			return a[c] === b && 0 === d || a[d] === b && 0 === c
		};
		if (!location || !location.hostname || !location.pathname) return !1;
		var e = document.location.hostname.replace(/^www\./,
				""),
			t = function() {
				for (var a, c = document.getElementsByTagName("meta"), d, b = 0, e = c.length; b < e; b++)
					if (d = c[b], "og:title" === d.getAttribute("property")) {
						a = d.getAttribute("content");
						break
					} a || (a = document.title || "Untitled");
				return a
			}(),
			b = {},
			b = function(a, c, b) {
				a.l1 = c;
				a.l2 = b;
				a.l3 = "__page__";
				a.l4 = "-";
				return a
			}(b, e, t),
			m = (new Date).getTime(),
			n = Math.floor(Math.random() * Math.pow(10, 12)),
			p, f;
		f = D();
		p = f.a ? f.b : 0;
		b.zmoatab_cm = p;
		b.t = m;
		b.de = n;
		b.zMoatAB_SNPT = !0;
		var q;
		q = p ? p : 1;
		var u;
		u = f ? f.a ? !0 : !1 : !0;
		var e = [],
			v = (new Date).getTime().toString(35),
			w = [l(b.l1), l(b.l2), l(b.l3), l(b.l4)].join(":"),
			t = /zct[a-z0-9]+/i,
			g = "",
			h;
		for (h in b) b.hasOwnProperty(h) && h.match(t) && (g += "&" + h + "=" + b[h]);
		var A = document.referrer.match(/^([^:]{2,}:\/\/[^\/]*)/),
			r = A ? A[1] : document.referrer,
			x = ["e=17", "d=" + encodeURIComponent(w), "de=" + n, "t=" + m, "i=CBCCONTENT2", "cm=" + q, String("j=" + encodeURIComponent(r) + g), "mp=1", "ac=1", "pl=1", "bq=10", "vc=2"],
			v = "https://5j8ujmnywdoc-a.akamaihd.net/" + v + ".gif?",
			y = function(a) {
				for (var c = "", b = 0; b < a.length; b++) c += (0 === b ? "" : "&") + a[b];
				return c
			}(function(a) {
				for (var b =
						0; b < a.length; b++) {
					var d = Math.floor(Math.random() * (a.length - b) + b),
						e = a[b],
						f = z(a, b, d, x[1]);
					z(a, b, d, x[0]) || f ? b-- : (a[b] = a[d], a[d] = e)
				}
				return a
			}(x)),
			y = v + y + "&cs=0",
			E = "https://px.moatads.com/pixel.gif?e=17&d=" + encodeURIComponent(w) + "&de=" + n + "&t=" + m + "&i=CBCCONTENT2&cm=" + q + "&j=" + encodeURIComponent(r) + g + "&mp=0&ac=1&pl=1&bq=10&ad_type=img&vc=2&cs=0",
			F = "https://px.moatads.com/pixel.gif?e=17&d=" + encodeURIComponent(w) + "&de=" + n + "&t=" + m + "&i=CBCCONTENT2&cm=" + q + "&j=" + encodeURIComponent(r) + g + "&ku=1&ac=1&pl=1&bq=10&ad_type=img&vc=2&cs=0";
		u && ((new Image).src = y, (new Image).src = E);
		for (var B in b) e.push(B + "=" + encodeURIComponent(b[B]));
		var e = e.join("&"),
			e = e + "&vc=2",
			k = document.createElement("script");
		k.type = "text/javascript";
		k.async = !0;
		u && (k.onerror = function() {
			(new Image).src = F
		});
		var C = document.getElementsByTagName("script")[0];
		C.parentNode.insertBefore(k, C);
		k.src = "https://z.moatads.com/cbc907UvMO43/moatcontent.js#" + e
	} catch (a) {
		try {
			var G = "//pixel.moatads.com/pixel.gif?e=24&d=data%3Adata%3Adata%3Adata&i=MOATCONTENTABSNIPPET1" + g + "&vc=2&ac=1&k=" +
				encodeURIComponent(a) + "&j=" + encodeURIComponent(r) + "&cs=" + (new Date).getTime();
			(new Image).src = G
		} catch (c) {}
	}
})();
