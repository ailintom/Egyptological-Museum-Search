/*
 * Egyptological Museum Downloader Bookmarklet 
 * v. 1.5 (16 July 2023)
 * 
 * The aim of this bookmarklet is to facilitate downloading images of Egyptian 
 * objects from the websites of major museums. 
 * 
 * Usage: 
 * 1) Click on the link below and drag it to your browser’s bookmarks bar
 *    (also called 'favorites bar' in some browsers).
 * 2) Open an object page of an online museum catalogue, which contains images
 * 3) Press the link 'Museum Downloader' on your bookmarks bar. 
 * 4) Depending on the museum, the tool will either download all available images
 *    or open all images in new tabs.
 * 5) Your browser may ask if you allow the website to download multiple files 
 *    or to open multiple pop ups. If you do not allow, the bookmarklet does not 
 *    work. However, you only have to allow once, for each museum.
 * 6) Look in your downloads folder.
 * 
 * The bookmarklet downloads images from the British Museum, the Boston Museum 
 * of Fine Arts, the Louvre, National Museum of Antiquities (Leiden), Petrie 
 * Museum, Egyptian Museum (Turin), Royal Museums of Art and History 
 * (Brussels), Manchester Museum, National Museums Scotland (Edinburgh),
 * and the Bible and Orient Museum (Fribourg).
 * 
 * It opens all images in new tabs for the Brooklyn Museum, Metropolitan Museum,
 * Oriental Institute Museum (Chicago).
 * 
 * Copyright 2023 Alexander Ilin-Tomich
 *
 ** Licensed under the MIT License. 
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy 
 * of this software and associated documentation files (the "Software"), to deal 
 * in the Software without restriction, including without limitation the rights 
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell 
 * copies of the Software, and to permit persons to whom the Software is furnished 
 * to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in 
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, 
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE 
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER 
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, 
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE 
 * SOFTWARE.
 */

var cu = window.location.href, x1 = new XMLHttpRequest(), b = document.body, match, i, url;
function dl(url, target) {
    var a = document.createElement('a');
    a.style.display = 'none';
    b.appendChild(a);
    if (target === true) {
        a.target = "_blank";
    } else {
       var filename = (target && target.exec(b.innerHTML)) ? target.exec(b.innerHTML)[1].trim() : 'download';
       a.setAttribute('download', filename + '.jpg');
    }
    a.href = url;
    a.click();

    b.removeChild(a);
}

function dlall(html, rein, pref, suff, target) {
    var re = rein;
    var s = new Set(html.match(re));
    [...s].forEach(function (val, index) {
        setTimeout(function () {
            var m = re.exec(val);
            re.lastIndex = 0;
            dl(pref + m[1].replace(/&amp;/g, "&") + suff, target);
        }, 120 * (index + 1));
    });
}
if (/britishmuseum\.org/.test(cu)) {
    url = cu.replace(/.*\//gm, "/api/_object?id=");
    x1.onreadystatechange = function () {
        if (x1.readyState === 4 && x1.status === 200) {
            if (x1.responseText) {
                var js = JSON.parse(x1.responseText);
                js['hits']['hits'][0]['_source']['multimedia'].forEach(function (item, index) {
                    setTimeout(function () {
                        dl('/api/_image-download?id=' + item['admin']['id'], /="object-detail__data-description">(.+?)</);
                    }, 120 * (index + 1));
                });
            }
        }
    };
    x1.open("GET", url, true);
    x1.send(null);

} else if (/brooklynmuseum\.org/.test(cu)) {
    dlall(b.innerHTML, /data-full-img-url="(.*)"/gm, "", "", true);
} else if (/mfa\.org/.test(cu)) {
    var re = /(\/internal.*?3Aformat%3D)postage/gm;
    if (re.test(b.innerHTML)) {
        dlall(b.innerHTML, re, "", "full", /Accession Number<\/span><span class="detailFieldValue">(.+?)</);
    } else {
        dl(document.getElementsByName("og:image")[0].getAttribute("content"), /Accession Number<\/span><span class="detailFieldValue">(.+?)</);
        //dlall(document.head.innerHTML, /mfa\.org(\/.*?)" name="og:image/gm, "", "", 0);
    }
} else if (/louvre\.fr/.test(cu)) {
    dlall(b.innerHTML, /data-api-dl="(.*?)"/gm, "", "", /Numéro principal\s+?:\s+?<\/span>(.+?)</);
} else if (/rmo\.nl/.test(cu)) {
    dlall(b.innerHTML, /(\/imageproxy\/jpg\/.*?)"/gm, "", "", /Inventarisnummer:(.+?)</);
} else if (/collections\.ucl\.ac\.uk/.test(cu)) {
    dlall(b.innerHTML, /src="(.*?)&amp;width/gm, "", "", /Number<\/div><div class="value">LDUCE-(UC.+?)</);
} else if (/metmuseum\.org/.test(cu)) {
    dlall(b.innerHTML, /data-superjumboimage="(.*?)"/gm, "", "", true);
} else if (/museoegizio\.it/.test(cu)) {
    dlall(b.innerHTML, /Download <a href="(.*?)"/gm, "", "", /col-lg-9">\s*?<span class="value">(.+)</);
} else if (/isac-idb\.uchicago\.edu/.test(cu)) {
    dlall(b.innerHTML, /cycle-slideshow-image-container" href="(.*?)"/gm, "", "", true);
} else if (/carmentis\.kmkg-mrah\.be/.test(cu)) {
    var re = /href="(.*?)" data-fancybox="images/gm;
    var pl = document.getElementById("referenceTab-03");
    if (pl && pl.classList.contains("referenceTabItem")) {
        url = pl.getElementsByTagName('A')[0].getAttribute('href');

        x1.onreadystatechange = function () {
            if (x1.readyState === 4 && x1.status === 200) {
                if (x1.responseText) {
                    dlall(x1.responseText, re, "", "", /inv=(.+?)&/);
                }
            }
        };
        x1.open("GET", url, true);
        x1.send(null);
    } else {
        dlall(b.innerHTML, re, "", "", /inv=(.+?)&/);
    }
} else if (/harbour\.man\.ac\.uk/.test(cu)) {
    dlall(b.innerHTML, /\?irn=(\d*)/gm, "/emuweb/objects/common/webmedia.php?irn=", "", 0);
} else if (/bible-orient-museum\.ch/.test(cu)) {
    dlall(b.innerHTML, /background-image: url\((.*?)\)/gm, "", "", 0);
} else if (/nms.ac\.uk/.test(cu)) {
    dlall(b.innerHTML, /data-zoom-image="(.*?)"/gm, "", "", /Museum reference<\/h3><p>(.+?)</);
}