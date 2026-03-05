<?php
/**
 * Image processing utilities using ImageMagick
 */

/**
 * Resize an image if it exceeds the maximum file size.
 * Uses iterative quality reduction to achieve target size.
 * Converts all formats to JPEG for consistent compression.
 * 
 * @param string $sourcePath Path to the source image file
 * @param int $maxSizeBytes Maximum file size in bytes
 * @param string|null $mimeType MIME type of the image (auto-detected if null)
 * @return string|null Path to the resized image, or null on failure
 */
function resizeImageIfNeeded(string $sourcePath, int $maxSizeBytes, ?string $mimeType = null): ?string
{
    // Check if file already meets size requirement
    if (filesize($sourcePath) <= $maxSizeBytes) {
        return $sourcePath;
    }
    
    // Auto-detect MIME type if not provided
    if ($mimeType === null) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $sourcePath);
        finfo_close($finfo);
    }
    
    try {
        $image = new Imagick($sourcePath);
        
        // Auto-orient based on EXIF data (important for phone photos)
        // Must be done before stripping metadata
        $orientation = $image->getImageOrientation();
        if ($orientation !== Imagick::ORIENTATION_TOPLEFT && $orientation !== Imagick::ORIENTATION_UNDEFINED) {
            $image->autoOrientImage();
        }
        
        // Flatten image to handle transparency (replace with white background)
        // This is necessary for formats like PNG with alpha channels
        if ($image->getImageAlphaChannel()) {
            $image->setImageBackgroundColor('white');
            $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        }
        
        // Strip metadata to reduce file size
        $image->stripImage();
        
        // Set output format to JPEG for best compression control
        $image->setImageFormat('jpeg');
        
        // Iteratively reduce quality until file size is acceptable
        $quality = 90;
        $minQuality = 20;
        $qualityStep = 10;
        
        // Determine output path (change extension to .jpg)
        $outputPath = preg_replace('/\.[^.]+$/', '.jpg', $sourcePath);
        
        while ($quality >= $minQuality) {
            $image->setImageCompressionQuality($quality);
            
            // Write to temp file to check size
            $tempPath = $sourcePath . '.tmp.jpg';
            $image->writeImage($tempPath);
            
            $newSize = filesize($tempPath);
            if ($newSize <= $maxSizeBytes) {
                // Success - move to final location
                $image->destroy();
                
                // Move temp file to output path
                if ($outputPath !== $sourcePath && file_exists($sourcePath)) {
                    unlink($sourcePath);
                }
                rename($tempPath, $outputPath);
                return $outputPath;
            }
            
            // Clean up temp file and try lower quality
            unlink($tempPath);
            $quality -= $qualityStep;
        }
        
        // If we've reached minimum quality and still too large,
        // save at minimum quality as last resort
        $image->setImageCompressionQuality($minQuality);
        $tempPath = $sourcePath . '.tmp.jpg';
        $image->writeImage($tempPath);
        $image->destroy();
        
        // Move temp file to output path
        if ($outputPath !== $sourcePath && file_exists($sourcePath)) {
            unlink($sourcePath);
        }
        rename($tempPath, $outputPath);
        return $outputPath;
        
    } catch (ImagickException $e) {
        error_log("ImageMagick error in resizeImageIfNeeded: " . $e->getMessage());
        return null;
    }
}
