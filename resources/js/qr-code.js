const QR_SIZE = 41;
const DATA_CODEWORDS = 136;
const ERROR_CODEWORDS_PER_BLOCK = 18;

function multiply(a, b) {
    let result = 0;
    for (let bit = 0; bit < 8; bit++) {
        if (b & 1) result ^= a;
        a <<= 1;
        if (a & 0x100) a ^= 0x11d;
        b >>>= 1;
    }
    return result;
}

function reedSolomonDivisor(degree) {
    let polynomial = [1];
    let root = 1;

    for (let index = 0; index < degree; index++) {
        const next = new Array(polynomial.length + 1).fill(0);
        polynomial.forEach((coefficient, position) => {
            next[position] ^= coefficient;
            next[position + 1] ^= multiply(coefficient, root);
        });
        polynomial = next;
        root = multiply(root, 2);
    }

    return polynomial.slice(1);
}

function reedSolomonRemainder(data, divisor) {
    const remainder = new Array(divisor.length).fill(0);
    for (const byte of data) {
        const factor = byte ^ remainder.shift();
        remainder.push(0);
        divisor.forEach((coefficient, index) => {
            remainder[index] ^= multiply(coefficient, factor);
        });
    }
    return remainder;
}

function makeCodewords(text) {
    const bytes = Array.from(new TextEncoder().encode(text));
    if (bytes.length > 134) throw new Error('Tautan terlalu panjang untuk QR kelas.');

    const bits = [];
    const appendBits = (value, length) => {
        for (let shift = length - 1; shift >= 0; shift--) bits.push((value >>> shift) & 1);
    };

    appendBits(0b0100, 4); // Byte mode
    appendBits(bytes.length, 8); // Character count for versions 1–9
    bytes.forEach((byte) => appendBits(byte, 8));
    appendBits(0, Math.min(4, DATA_CODEWORDS * 8 - bits.length));
    while (bits.length % 8) bits.push(0);

    const data = [];
    for (let offset = 0; offset < bits.length; offset += 8) {
        data.push(bits.slice(offset, offset + 8).reduce((value, bit) => (value << 1) | bit, 0));
    }
    for (let pad = 0; data.length < DATA_CODEWORDS; pad++) data.push(pad % 2 === 0 ? 0xec : 0x11);

    const divisor = reedSolomonDivisor(ERROR_CODEWORDS_PER_BLOCK);
    const blocks = [data.slice(0, 68), data.slice(68)];
    const errorBlocks = blocks.map((block) => reedSolomonRemainder(block, divisor));
    const interleaved = [];

    for (let index = 0; index < 68; index++) blocks.forEach((block) => interleaved.push(block[index]));
    for (let index = 0; index < ERROR_CODEWORDS_PER_BLOCK; index++) errorBlocks.forEach((block) => interleaved.push(block[index]));

    return interleaved.flatMap((byte) => Array.from({ length: 8 }, (_, bit) => (byte >>> (7 - bit)) & 1));
}

function setFunction(matrix, reserved, x, y, value) {
    if (x < 0 || y < 0 || x >= QR_SIZE || y >= QR_SIZE) return;
    matrix[y][x] = value;
    reserved[y][x] = true;
}

function addFinder(matrix, reserved, centerX, centerY) {
    for (let dy = -1; dy <= 7; dy++) {
        for (let dx = -1; dx <= 7; dx++) {
            const distance = Math.max(Math.abs(dx - 3), Math.abs(dy - 3));
            setFunction(matrix, reserved, centerX + dx, centerY + dy, distance !== 2 && distance !== 4);
        }
    }
}

function addFormatBits(matrix, reserved, mask) {
    const data = (0b01 << 3) | mask; // Error correction level L
    let remainder = data;
    for (let index = 0; index < 10; index++) {
        remainder = (remainder << 1) ^ ((remainder >>> 9) * 0x537);
    }
    const format = ((data << 10) | remainder) ^ 0x5412;
    const bit = (index) => ((format >>> index) & 1) !== 0;

    for (let index = 0; index <= 5; index++) setFunction(matrix, reserved, 8, index, bit(index));
    setFunction(matrix, reserved, 8, 7, bit(6));
    setFunction(matrix, reserved, 8, 8, bit(7));
    setFunction(matrix, reserved, 7, 8, bit(8));
    for (let index = 9; index < 15; index++) setFunction(matrix, reserved, 14 - index, 8, bit(index));

    for (let index = 0; index < 8; index++) setFunction(matrix, reserved, QR_SIZE - 1 - index, 8, bit(index));
    for (let index = 8; index < 15; index++) setFunction(matrix, reserved, 8, QR_SIZE - 15 + index, bit(index));
    setFunction(matrix, reserved, 8, QR_SIZE - 8, true);
}

function makeBaseMatrix() {
    const matrix = Array.from({ length: QR_SIZE }, () => new Array(QR_SIZE).fill(false));
    const reserved = Array.from({ length: QR_SIZE }, () => new Array(QR_SIZE).fill(false));

    for (let index = 0; index < QR_SIZE; index++) {
        setFunction(matrix, reserved, 6, index, index % 2 === 0);
        setFunction(matrix, reserved, index, 6, index % 2 === 0);
    }

    addFinder(matrix, reserved, 0, 0);
    addFinder(matrix, reserved, QR_SIZE - 7, 0);
    addFinder(matrix, reserved, 0, QR_SIZE - 7);

    for (let dy = -2; dy <= 2; dy++) {
        for (let dx = -2; dx <= 2; dx++) {
            setFunction(matrix, reserved, 34 + dx, 34 + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
        }
    }

    addFormatBits(matrix, reserved, 0);
    return { matrix, reserved };
}

function maskCondition(mask, x, y) {
    switch (mask) {
        case 0: return (x + y) % 2 === 0;
        case 1: return y % 2 === 0;
        case 2: return x % 3 === 0;
        case 3: return (x + y) % 3 === 0;
        case 4: return (Math.floor(y / 2) + Math.floor(x / 3)) % 2 === 0;
        case 5: return (x * y) % 2 + (x * y) % 3 === 0;
        case 6: return ((x * y) % 2 + (x * y) % 3) % 2 === 0;
        default: return ((x + y) % 2 + (x * y) % 3) % 2 === 0;
    }
}

function placeData(matrix, reserved, bits, mask) {
    let bitIndex = 0;
    for (let right = QR_SIZE - 1; right >= 1; right -= 2) {
        if (right === 6) right = 5;
        const upward = ((right + 1) & 2) === 0;
        for (let vertical = 0; vertical < QR_SIZE; vertical++) {
            const y = upward ? QR_SIZE - 1 - vertical : vertical;
            for (let offset = 0; offset < 2; offset++) {
                const x = right - offset;
                if (reserved[y][x]) continue;
                const value = bits[bitIndex++] === 1;
                matrix[y][x] = value !== maskCondition(mask, x, y);
            }
        }
    }
}

function penaltyScore(matrix) {
    let score = 0;
    const lines = [...matrix, ...matrix[0].map((_, x) => matrix.map((row) => row[x]))];

    for (const line of lines) {
        let runColor = line[0];
        let runLength = 1;
        for (let index = 1; index < line.length; index++) {
            if (line[index] === runColor) {
                runLength++;
                if (runLength === 5) score += 3;
                else if (runLength > 5) score++;
            } else {
                runColor = line[index];
                runLength = 1;
            }
        }
        const pattern = line.map((cell) => cell ? '1' : '0').join('');
        for (let index = 0; index <= pattern.length - 11; index++) {
            const window = pattern.slice(index, index + 11);
            if (window === '10111010000' || window === '00001011101') score += 40;
        }
    }

    for (let y = 0; y < QR_SIZE - 1; y++) {
        for (let x = 0; x < QR_SIZE - 1; x++) {
            const value = matrix[y][x];
            if (matrix[y][x + 1] === value && matrix[y + 1][x] === value && matrix[y + 1][x + 1] === value) score += 3;
        }
    }

    const dark = matrix.reduce((count, row) => count + row.filter(Boolean).length, 0);
    score += Math.floor(Math.abs((dark * 100) / (QR_SIZE * QR_SIZE) - 50) / 5) * 10;
    return score;
}

export function createQrMatrix(text) {
    const bits = makeCodewords(text);
    const base = makeBaseMatrix();
    let bestMatrix = null;
    let bestScore = Infinity;

    for (let mask = 0; mask < 8; mask++) {
        const matrix = base.matrix.map((row) => [...row]);
        const reserved = base.reserved.map((row) => [...row]);
        placeData(matrix, reserved, bits, mask);
        addFormatBits(matrix, reserved, mask);
        const score = penaltyScore(matrix);
        if (score < bestScore) {
            bestScore = score;
            bestMatrix = matrix;
        }
    }

    return bestMatrix;
}

export function drawQrCode(canvas, text, size = 224) {
    const matrix = createQrMatrix(text);
    const context = canvas.getContext('2d');
    const quietZone = 4;
    const moduleCount = matrix.length + quietZone * 2;
    const cellSize = size / moduleCount;

    canvas.width = size;
    canvas.height = size;
    context.fillStyle = '#fff';
    context.fillRect(0, 0, size, size);
    context.fillStyle = '#14243b';

    matrix.forEach((row, y) => row.forEach((dark, x) => {
        if (dark) {
            const left = Math.floor((x + quietZone) * cellSize);
            const top = Math.floor((y + quietZone) * cellSize);
            const right = Math.ceil((x + quietZone + 1) * cellSize);
            const bottom = Math.ceil((y + quietZone + 1) * cellSize);
            context.fillRect(left, top, right - left, bottom - top);
        }
    }));
}
