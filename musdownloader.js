/*
 * Egyptological Museum Downloader Bookmarklet 
 * v. 1.3 (29 October 2021)
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
 * Copyright 2021 Alexander Ilin-Tomich
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

var cu = window.location.href, b = document.body, match, i, url;
function dl(url, target) {
    var a = document.createElement('a');
    a.style.display = 'none';
    b.appendChild(a);
    if (target) {
        a.target = "_blank";
    } else {
        a.setAttribute('download', 'download.jpg');
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

    var re = /\/mid_(\d*)_(\d*).jpg/gm;

    var s = new Set(b.innerHTML.match(re));
    [...s].forEach(function (val, index) {

        setTimeout(function () {
            match = re.exec(val);
            re.lastIndex = 0;
            dl('/api/_image-download?id=' + Number(match[1].concat(match[2])), 0);
        }, 120 * (index + 1));

    });
} else if (/brooklynmuseum\.org/.test(cu)) {
    dlall(b.innerHTML, /data-full-img-url="(.*)"/gm, "", "", true);
} else if (/mfa\.org/.test(cu)) {
    var re = /(\/internal.*?3Aformat%3D)postage/gm;
    if (re.test(b.innerHTML)) {
        dlall(b.innerHTML, re, "", "full", 0);
    } else {
        dl(document.getElementsByName("og:image")[0].getAttribute("content"), 0);
        //dlall(document.head.innerHTML, /mfa\.org(\/.*?)" name="og:image/gm, "", "", 0);
    }
} else if (/louvre\.fr/.test(cu)) {
    dlall(b.innerHTML, /data-api-dl="(.*?)"/gm, "", "", 0);
} else if (/rmo\.nl/.test(cu)) {
    dlall(b.innerHTML, /(\/imageproxy\/jpg\/.*?)"/gm, "", "", 0);
} else if (/petriecat\.museums\.ucl\.ac\.uk/.test(cu)) {
    var maxphotos = /maxphotos=(\d*)/gm.exec(b.innerHTML)[1];
    match = /object_images\/mid(\/.*?)1.jpg"/gm.exec(b.innerHTML);
    if (maxphotos == 1 || match === null) {
        dlall(b.innerHTML, /object_images\/mid(\/.*?.jpg)"/gm, "/object_images/full", "");
    } else {
        for (i = 1; i <= maxphotos; i += 1) {
            dl("/object_images/full" + match[1] + i + '.jpg', 0);
        }
    }
} else if (/metmuseum\.org/.test(cu)) {
    dlall(b.innerHTML, /data-superjumboimage="(.*?)"/gm, "", "", true);
} else if (/museoegizio\.it/.test(cu)) {
    dlall(b.innerHTML, /Download <a href="(.*?)"/gm, "", "", 0);
} else if (/oi-idb\.uchicago\.edu/.test(cu)) {
    dlall(b.innerHTML, /cycle-slideshow-image-container" href="(.*?)"/gm, "", "", true);
} else if (/carmentis\.kmkg-mrah\.be/.test(cu)) {
    var re = /href="(.*?)" data-fancybox="images/gm;
    var pl = document.getElementById("referenceTab-03");
    if (pl.classList.contains("referenceTabItem")) {
        url = pl.getElementsByTagName('A')[0].getAttribute('href');
        var x1 = new XMLHttpRequest();
        x1.onreadystatechange = function () {
            if (x1.readyState === 4 && x1.status === 200) {
                if (x1.responseText) {
                    dlall(x1.responseText, re, "", "", 0);
                }
            }
        };
        x1.open("GET", url, true);
        x1.send(null);
    } else {
        dlall(b.innerHTML, re, "", "", 0);
    }
} else if (/harbour\.man\.ac\.uk/.test(cu)) {
    dlall(b.innerHTML, /\?irn=(\d*)/gm, "/emuweb/objects/common/webmedia.php?irn=", "", 0);
} else if (/bible-orient-museum\.ch/.test(cu)) {
    dlall(b.innerHTML, /background-image: url\((.*?)\)/gm, "", "", 0);
} else if (/nms.ac\.uk/.test(cu)) {
    dlall(b.innerHTML, /data-zoom-image="(.*?)"/gm, "", "", 0);
}