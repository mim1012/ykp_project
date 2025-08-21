import React, { useState, useRef, useEffect, memo } from 'react';
import { motion } from 'framer-motion';
import { useIntersectionObserver } from '../../hooks/usePerformance';

// Optimized image component with lazy loading and blur-up effect
export const OptimizedImage = memo(({
  src,
  alt,
  className = '',
  placeholderSrc,
  blurHash,
  width,
  height,
  loading = 'lazy',
  onLoad,
  onError,
  ...props
}) => {
  const [isLoaded, setIsLoaded] = useState(false);
  const [hasError, setHasError] = useState(false);
  const [imageSrc, setImageSrc] = useState(placeholderSrc || '');
  
  const { elementRef, isInView } = useIntersectionObserver({
    threshold: 0.1,
    triggerOnce: true
  });

  useEffect(() => {
    if (isInView && src && !isLoaded) {
      const img = new Image();
      
      img.onload = () => {
        setImageSrc(src);
        setIsLoaded(true);
        onLoad?.(img);
      };
      
      img.onerror = () => {
        setHasError(true);
        onError?.(img);
      };
      
      img.src = src;
    }
  }, [isInView, src, isLoaded, onLoad, onError]);

  const imageVariants = {
    loading: {
      scale: 1.1,
      opacity: 0
    },
    loaded: {
      scale: 1,
      opacity: 1,
      transition: {
        duration: 0.3,
        ease: 'easeOut'
      }
    }
  };

  if (hasError) {
    return (
      <div 
        ref={elementRef}
        className={`bg-gray-200 flex items-center justify-center ${className}`}
        style={{ width, height }}
      >
        <span className="text-gray-400 text-sm">이미지 로드 실패</span>
      </div>
    );
  }

  return (
    <div 
      ref={elementRef}
      className={`relative overflow-hidden ${className}`}
      style={{ width, height }}
    >
      {blurHash && !isLoaded && (
        <div
          className="absolute inset-0 bg-gray-200"
          style={{
            backgroundImage: `url("data:image/svg+xml;base64,${blurHash}")`,
            backgroundSize: 'cover',
            backgroundPosition: 'center'
          }}
        />
      )}
      
      {!isLoaded && !blurHash && (
        <div className="absolute inset-0 bg-gray-200 animate-pulse" />
      )}
      
      <motion.img
        src={imageSrc}
        alt={alt}
        className={`w-full h-full object-cover ${isLoaded ? 'opacity-100' : 'opacity-0'}`}
        variants={imageVariants}
        initial="loading"
        animate={isLoaded ? "loaded" : "loading"}
        loading={loading}
        {...props}
      />
      
      {!isLoaded && (
        <div className="absolute inset-0 flex items-center justify-center">
          <div className="w-6 h-6 border-2 border-gray-300 border-t-blue-600 rounded-full animate-spin" />
        </div>
      )}
    </div>
  );
});

OptimizedImage.displayName = 'OptimizedImage';

// Avatar component with optimized loading
export const Avatar = memo(({
  src,
  alt,
  size = 'md',
  fallback,
  className = '',
  ...props
}) => {
  const sizes = {
    sm: 'w-8 h-8',
    md: 'w-10 h-10',
    lg: 'w-12 h-12',
    xl: 'w-16 h-16'
  };

  const [hasError, setHasError] = useState(false);

  const handleError = () => {
    setHasError(true);
  };

  if (hasError || !src) {
    return (
      <div className={`${sizes[size]} rounded-full bg-gray-300 flex items-center justify-center ${className}`}>
        {fallback || (
          <span className="text-gray-600 font-medium">
            {alt?.[0]?.toUpperCase() || '?'}
          </span>
        )}
      </div>
    );
  }

  return (
    <OptimizedImage
      src={src}
      alt={alt}
      className={`${sizes[size]} rounded-full ${className}`}
      onError={handleError}
      {...props}
    />
  );
});

Avatar.displayName = 'Avatar';

// Responsive image component with multiple sources
export const ResponsiveImage = memo(({
  src,
  srcSet,
  sizes,
  webpSrc,
  webpSrcSet,
  alt,
  className = '',
  ...props
}) => {
  return (
    <picture>
      {webpSrc && (
        <source
          srcSet={webpSrcSet || webpSrc}
          sizes={sizes}
          type="image/webp"
        />
      )}
      <OptimizedImage
        src={src}
        srcSet={srcSet}
        sizes={sizes}
        alt={alt}
        className={className}
        {...props}
      />
    </picture>
  );
});

ResponsiveImage.displayName = 'ResponsiveImage';

// Image gallery with lazy loading
export const ImageGallery = memo(({ images, columns = 3, gap = 4 }) => {
  const gridClass = `grid grid-cols-${columns} gap-${gap}`;
  
  return (
    <div className={gridClass}>
      {images.map((image, index) => (
        <motion.div
          key={image.id || index}
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: index * 0.1 }}
          className="aspect-square"
        >
          <OptimizedImage
            src={image.src}
            alt={image.alt}
            className="w-full h-full rounded-lg"
            placeholderSrc={image.placeholder}
          />
        </motion.div>
      ))}
    </div>
  );
});

ImageGallery.displayName = 'ImageGallery';