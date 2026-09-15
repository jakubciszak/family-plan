const LARGEST = { avatar: 512, backdrop: 1600 };

const QUALITY = { avatar: 0.9, backdrop: 0.82 };

const readAsImage = (file) => new Promise((resolve, reject) => {
    const reader = new FileReader();

    reader.onerror = () => reject(new Error('unreadable'));
    reader.onload = () => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('not an image'));
        image.src = reader.result;
    };

    reader.readAsDataURL(file);
});

/**
 * Shrinks a picked file in the browser, so only something small and sane ever leaves the device.
 */
export const shrinkForUpload = async (file, purpose) => {
    const image = await readAsImage(file);
    const longest = LARGEST[purpose] || LARGEST.avatar;
    const scale = Math.min(1, longest / Math.max(image.width, image.height));

    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(image.width * scale));
    canvas.height = Math.max(1, Math.round(image.height * scale));

    const brush = canvas.getContext('2d');
    brush.drawImage(image, 0, 0, canvas.width, canvas.height);

    return canvas.toDataURL('image/jpeg', QUALITY[purpose] || 0.9);
};
