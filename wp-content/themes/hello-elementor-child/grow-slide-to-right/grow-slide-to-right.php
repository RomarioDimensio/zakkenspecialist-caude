<div class="dg-container business-fun-features">
	<div class="fixed-wrapper">
		<div class="fixed-content">
			<div class="left"> <h2 class="left-inner"> Wij zijn </h2> </div>
			<div class="middle image scroll-sequence">
				<div class="canvas-container">
			    	<canvas id="dg-canvas-features-image" class="image-sequence"></canvas>
			    </div>
	      		<div class="final-frame">
	      			<div class="final-frame-image" style="background-image: url('<?php echo get_stylesheet_directory_uri(); ?>/grow-slide-to-right/images-fun-features/013.webp');"></div>
	      		</div>
			</div>
			<div class="right">
				<h2 class="right-inner"> Dimensio </h2>
			</div>

		</div>
	</div>
</div>

<div class="dg-container-spacer"></div>
<div class="dg-container-spacer"></div>
<div class="dg-container-spacer"></div>

<div class="dg-hero-title">
	<h1>Elke dag een topprestatie leveren voor onze klanten met de best passende verpakkingsoplossing niet alleen voor het gebruik, maar ook voor het milieu.</h1>
</div>

<div class="dg-slider-container">
	<div class="slider">
		<div class="slide slide1">
			<div class="scroll-grow-wrapper">
				<img src="/wp-content/uploads/2025/06/dezakkenspecialist_mobile.webp" alt="Awesome image" class="img">
				<h1> onze producten in de praktijk </h1>
			</div>
		</div>

  		<div class="slide slide2">
  			<div class="horizontal-scroll-section">
			    <div class="card">Card 1</div>
			    <div class="card">Card 2</div>
			    <div class="card">Card 3</div>
			  </div>
  		</div>
	</div>
</div>

<style>

.dg-slider-container, .dg-container, .dg-container-spacer, .dg-hero-title {
	position: relative;
	overflow: visible;
	height: 100vh;
}

.dg-hero-title {
	display: flex;
	align-items: center;
	justify-content: center;
	background-color: #f0ebe6;
}

.dg-hidden {
	visibility: hidden;
	opacity: 0;
}

.business-fun-features {
	height: 200vh;
	display: flex;
	align-items: center;
}

.business-fun-features .fixed-wrapper {
	position: fixed;
	top: 0;
	left: 0;
	height: 100vh;
	width: 100%;

	display: flex;
  align-items: center;
  padding: 20px 40px;
}

.business-fun-features .fixed-wrapper .final-frame {
	position: absolute;
	top: 0;
	left: 0;

	opacity: 0;
	visibility: hidden;
}

.business-fun-features .fixed-wrapper .final-frame.visible {
	opacity: 1;
	visibility: visible;
}


.business-fun-features .fixed-wrapper .final-frame .final-frame-image {
	height: 100vh;
  	width: 100vw;

  	background-size: cover;
  	background-repeat: no-repeat;
  	clip-path: inset(31.3% 42.5% 31.3% 42.55%);
}


.business-fun-features .fixed-content {
	display: flex;
  	align-items: center;
  	flex-wrap: nowrap;
	width: 100%;
	height: 100%;
}

.business-fun-features .left {
	flex: 1;
}

.business-fun-features .left .left-inner {
	display: inline-block;
}

.business-fun-features .right .right-inner {
	display: inline-block;
}


.business-fun-features .fixed-wrapper .canvas-container.zoom-mode {
  position: fixed;
  top: 0;
  left: 50%;
  transform: translateX(-50%) scale(1);
  transform-origin: center center;
  transition: none;
  opacity: 1;
  pointer-events: none;
}

.business-fun-features .middle {
	height: 350px;
  	width: 280px;
	transition: all 200ms ease-in-out;
}

.business-fun-features .right {
	text-align: right;
	flex: 1;
}

.dg-slider-container .slider {
	display: flex;
	width: 200vw; /* 100 vw x het aantal slides */
	height: 100%;
	transition: 
    	transform 0.2s cubic-bezier(0.16,1,0.3,1),
    	opacity 0.2s;
}

.dg-slider-container .scroll-grow-wrapper {
	display: flex;
	flex-wrap: nowrap;
	align-items: center;
	
  	width: 100%;          /* Fill the container's width */
  	height: 100%;         /* Maintain aspect ratio */
  	max-width: 100%;      /* Responsive */
  	max-height: 100%;
  	opacity: 0;
  	transform: scale(0.2);      /* Start small */
  	transform-origin: bottom center; /* Animate from bottom middle */
  	transition: 
    	transform 0.6s cubic-bezier(0.16,1,0.3,1),
    	opacity 0.4s;
  	will-change: transform, opacity;
}

.dg-slider-container .scroll-grow-wrapper .img {
	position: absolute;
	top: 0;
	object-fit: cover;
	width: 100%;
	height: 100%;
}
.dg-slider-container .slider .slide {
	width: 100vw;
	height: 100vh;
}

.slide2 {
	background: #aaaaaa52;
	display: flex;
}

.dg-slider-container .horizontal-scroll-section {
	display: flex;
  	align-items: center;
  	gap: 8px;
  	overflow-x: hidden;
  	padding: 20px;
  	transition: 
  		transform 0.6s cubic-bezier(0.16,1,0.3,1),
    	opacity 0.4s;
}

.dg-slider-container .horizontal-scroll-section .card {
  min-width: 300px;
  height: 400px;
  background: white;
  border-radius: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  box-shadow: 0 4px 32px rgba(0,0,0,0.08);

  /* animation from scout traveler */
  transition: transform .6s cubic-bezier(.38,.005,.215,1), opacity .6s cubic-bezier(.38,.005,.215,1);
}
</style>


<script>
	document.addEventListener("DOMContentLoaded", function() {
    const dgBusinessFeatureContainer = document.querySelector('.dg-container.business-fun-features');
    
    const canvasContainer = dgBusinessFeatureContainer.querySelector('.canvas-container');
	  const canvas = canvasContainer.querySelector('#dg-canvas-features-image');
    const ctx = canvas.getContext("2d");
    
    const finalFrameContainer = dgBusinessFeatureContainer.querySelector('.final-frame'); 
    const finalFrameImage = finalFrameContainer.querySelector('.final-frame-image');
    
    const frameCount = 12;
    const images = [];

    let loadedImages = 0;

    let currentFrameIndex = 0;
    let targetFrameIndex = 0;
    let lastFrame = -1;

    canvas.width = 280;
    canvas.height = 350;

    for (let i = 1; i <= frameCount; i++) {
        const img = new Image();
        const imageNumber = ("00" + i).slice(-3);

        img.src = "<?php echo get_stylesheet_directory_uri(); ?>/grow-slide-to-right/images-fun-features/" + imageNumber + ".webp"; // Update with actual path

        img.onload = () => {
            loadedImages++;
            
            if (loadedImages === frameCount) {
                drawFrame(0); // Initial draw once all images are ready
                animate();
            }
        };
        img.style = "object-fit: stretch;";

        images.push(img);
    }


    // The higher this value the longer the scroll sequence takes to finish
    let maxScrollForAnimation = dgBusinessFeatureContainer.clientHeight;

    window.addEventListener("scroll", () => {
        // window.innerheig x 2 zodat we dan 2x volledige scherm moeten scrollen voordat de eerste image sequence klaar is.
        const scrollFractionImageSequence = Math.min(window.scrollY / (window.innerHeight * 2), 3);

        const zoomStart = 0; // begin zoom here
  		const zoomEnd = 1.0;

  			// scrollFractionImageSequence 1 heeft dan betekent het dat de eerste seqeunce klaar is.
  			if (scrollFractionImageSequence >= 1 && scrollFractionImageSequence < 2) {

  				finalFrameContainer.classList.add('visible');
			    // const zoomProgress = (scrollFractionImageSequence - zoomStart) / (zoomEnd - zoomStart);
			    const zoomProgress = scrollFractionImageSequence - 1;

			    // From (initial) to 0% (fully revealed)
			    const top = 31.3 - 31.3 * zoomProgress;
			    const right = 42.5 - 42.5 * zoomProgress;
			    const bottom = 31.3 - 31.3 * zoomProgress;
			    const left = 42.55 - 42.55 * zoomProgress;

  				finalFrameImage.style.clipPath = `inset(${top}% ${right}% ${bottom}% ${left}%)`;

  				const scale = 1 + 0.1 * scrollFractionImageSequence;
  				finalFrameImage.style.scale = `scale(${scale})`;

			  } else if (scrollFractionImageSequence < 1 ) {

			  	finalFrameContainer.classList.remove('visible');
			  }  

			  if (scrollFractionImageSequence >= 2.5 ) {
			  	dgBusinessFeatureContainer.classList.add('dg-hidden');
			  } else {
			  	dgBusinessFeatureContainer.classList.remove('dg-hidden');
			  }

        targetFrameIndex = Math.min(frameCount - 1, Math.floor(scrollFractionImageSequence * frameCount));

        animateText();
    });


    window.addEventListener("resize", () => {
        maxScrollForAnimation = dgBusinessFeatureContainer.clientHeight; 
    });


    function drawFrame(frameIndex) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        const image = images[frameIndex];

        const canvasAspect = canvas.width / canvas.height;
        const imgAspect = image.width / image.height;

        let drawWidth, drawHeight, offsetX, offsetY;

        if (imgAspect > canvasAspect) {
            // Image is wider than canvas
            drawHeight = canvas.height;
            drawWidth = image.width * (canvas.height / image.height);
            offsetX = (canvas.width - drawWidth) / 2;
            offsetY = 0;
        } else {
            // Image is taller than canvas
            drawWidth = canvas.width;
            drawHeight = image.height * (canvas.width / image.width);
            offsetX = 0;
            offsetY = (canvas.height - drawHeight) / 2;
        }

        ctx.drawImage(image, offsetX, offsetY, drawWidth, drawHeight);
    }

    function animate() {
        requestAnimationFrame(animate);

        currentFrameIndex += (targetFrameIndex - currentFrameIndex) * 0.2;

        // Snap if difference is small
        if (Math.abs(targetFrameIndex - currentFrameIndex) < 0.1) {
            currentFrameIndex = targetFrameIndex;
        }

        const rounded = Math.round(currentFrameIndex);


        if (rounded !== lastFrame) {
            drawFrame(rounded);
            lastFrame = rounded;
        }
    }

	    const leftText = dgBusinessFeatureContainer.querySelector('.left');
    	const rightText = dgBusinessFeatureContainer.querySelector('.right');

		function animateText() {
		  // Get scroll progress relative to scroll-area
		  const rect = dgBusinessFeatureContainer.getBoundingClientRect();
		  const totalScroll = dgBusinessFeatureContainer.offsetHeight;

		  
		  // Scroll from top of area to bottom of container.
		  const scrollTop = window.scrollY - dgBusinessFeatureContainer.offsetTop;
		  const progress = Math.max(0, Math.min(1, scrollTop / totalScroll));

		  // Move from 0% (left edge) to 60vw (adjust as needed for padding)
		  const maxBaseWidth = (window.innerWidth - 50);

		  const maxMoveLeft = ( leftText.clientWidth - leftText.children[0].clientWidth );
		  leftText.style.transform = `translateX(${progress * maxMoveLeft}px)`;

		  const maxMoveRight = ( rightText.clientWidth - rightText.children[0].clientWidth );
		  rightText.style.transform = `translateX(-${progress * maxMoveRight}px)`;
		}

		window.addEventListener('resize', animateText);
		animateText(); // Initialize


		// CODE: For making the image grow and the scroll to the right with the cards
		const container = document.querySelector('.dg-slider-container');
	  
		const slider = container.querySelector('.slider');
		const growWrapper = slider.querySelector('.scroll-grow-wrapper');
		const horizontalFirstSlide = slider.querySelector('.horizontal-scroll-section');
		
		const windowHeight = window.innerHeight;

		const horizontalSlides = 1;
		
		// plus 1 slide, add the grow slide.
		const slidesAmount = horizontalSlides + 1;
		const horizontalScrollLength = window.innerWidth * horizontalSlides; // 1920
		const containerOffsetTop = container.offsetTop || container.closest('.e-parent').offsetTop;
	  const growSectionEnd = containerOffsetTop + windowHeight;

	  	    
		// add spacing in the slide for a smooth transistion.
		container.style.height = `${(slidesAmount + 1) * 100}vh`;
	  
		function handleScroll(event) {
	    const rect = container.getBoundingClientRect();
	    const documentWindowHeight = windowHeight || document.documentElement.clientHeight;
	    const scrollY = window.scrollY || window.pageYOffset;

	    // Calculate how much of the container is in view (0 = not at all, 1 = fully in view)
	    const visible =
	      Math.min(rect.bottom, documentWindowHeight) - Math.max(rect.top, 0);
	    const progress = Math.max(0, Math.min(visible / documentWindowHeight, 1));	    

	    // Scale from 0.5 (50%) to 1 (100%) as you scroll through the container
	    const scale = 0.5 + 0.55 * progress;
	    // add plus 0.2 so that the opacity finish faster
	    const opacity = 0.2 + progress;


	    if (rect.top > 0) {
	    	// Container is in view.

	    	growWrapper.style.transform = `scale(${Math.min(scale, 1)})`;
	    	growWrapper.style.opacity = Math.min(opacity, 1);

	    	slider.style.transform = `translate3d(0px, 0px, 0px)`;
	    	slider.style.position = "relative";


	    	const endValue = -150
	    	const horizontalSlideTranslateX = endValue * progress;
	    	horizontalFirstSlide.style.transform = `translate3d(${horizontalSlideTranslateX}px, 0px, 0px)`;
	    	
	    } else if (rect.top <= 0) {
	    	// top equals 0 means it is fully in screen and we want te enable horizontal scrolling. 
	    	// Pin the slider.
            const threshold = 2;

	    	const middleOffsetFromContainer = containerOffsetTop + documentWindowHeight / 2;
        let horizontalProgress = (scrollY - middleOffsetFromContainer) / horizontalScrollLength;
	    	horizontalProgress = Math.max(0, Math.min(horizontalProgress, 1));
	    	// horizontalScrollLength = 1920
	    	// That means we need to change to vertical scroll when we are translating -1920px.
	    	// So that the first slide has been completed

	    	let moveX = -horizontalProgress * window.innerWidth;

	    	if (horizontalProgress <= 1) {
	    		slider.style.transform = `translate3d(${moveX}px, 0px, 0px)`;
		    	slider.style.position = "fixed";
		    	slider.style.top = 0;
		    	slider.style.left = 0;
	    	}
	    }
		}
    
    window.addEventListener('scroll', handleScroll);
  	window.addEventListener('resize', handleScroll);
  	handleScroll(); // Initial call
	  
	});
</script>