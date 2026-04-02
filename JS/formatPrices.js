const linDim = [
  ["", "", "", "", "", "", "", "", "", "", "", ""],
  ["", "", "", "", "", "", "", "", "", "", "", ""],
  ["", "", "", "", "", "", "", "", "", "", "", ""],
  ["", "", "", "", "", "", "", "", "", "", "", ""],
  ["", "", "", "", "", "", "", "", "", "", "", ""],
  ["", "", "", "", "", "", "", "", "", "", "", ""],
  ["", "", "", "", "", "", "", "", "", "", "", ""],
  ["", "", "", "", "", "", "", "", "", "", "", ""],
  ["", "", "", "", "", "", "", "", "", "", "", ""],
  ["", "", "", "", "", "", "", "", "", "", "", ""],
  ["", "", "", "", "", "", "", "", "", "", "", ""],
  ["", "", "", "", "", "", "", "", "", "", ",", ""]
];

const prices = document.querySelectorAll('.product-price');

prices.forEach(function (priceElement) {
  let text = priceElement.textContent.replace(/[^\d]/g, "");
  if (!text) return;

  let glyphs = [];
  let oldNum = null;
  let reversed = text.split("").reverse();

  // 最初に円記号
  glyphs.push(linDim[0][11]);

  for (let i = 0; i < reversed.length;) {
    let current = parseInt(reversed[i]);
    let isFirst = (i === 0);

    if ((i + 1) % 4 === 0) {
      const comma = linDim[oldNum][10];
      glyphs.push(comma);
      oldNum = 10;
    }

    let row = (oldNum == null) ? 11 : oldNum;
    let col = (current === 0) ? 9 : current - 1;

    let glyph = linDim[row][col];
    glyphs.push(glyph);

    oldNum = col;
    i++;
  }

  const result = glyphs.reverse().join("");
  priceElement.textContent = result;
  priceElement.style.color = "red";
  priceElement.style.fontFamily = "'PokusMA', sans-serif";
});