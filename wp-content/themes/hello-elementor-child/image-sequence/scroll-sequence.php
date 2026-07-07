<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Scroll-Triggered Interactive Sequence</title>
    <style>
      html {
        scroll-behavior: smooth;
      }
      body {
        margin: 0;
        display: flex;
        flex-wrap: wrap;
      }
      h1 {
        margin: 0;
        color: #ffffff;
      }
      .section {
        padding: 50px;
        position: relative;
        height: 100vh;
        width: 100vw;
      }

      .imageSequenceContainer {
        height: 400vh;
        position: relative;
        width: 100vw;
      }

      .canvas-container {
        position: fixed;
        height: 100vh;
        width: 100vw;
      }

      canvas.image-sequence {
        position: relative;
      }

      .popup {
        position: absolute;
        background: white;
        padding: 10px;
        border-radius: 8px;
        box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        display: none;
      }
      .clickable-area {
        position: absolute;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: rgba(255, 0, 0, 0.5);
        cursor: pointer;
        display: none;
      }
      .section1 {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        z-index: 9999;
      }

      .section1 .column {
        display: flex;
        flex-direction: column;
      }

      .section1 .column h1 {
        margin: 0;
      }

      .section .videoLoop {
        position: absolute;
        top: 0;
        left: 0;
        z-index: 0;
      }

      .section.sectionMovie {
        display: flex;
        justify-content: space-between;
      }

      .section.sectionMovie .column {
        z-index: 1;
      }
  </style>
</head>
<body>
  <div class="imageSequenceContainer">
    <div class="canvas-container">
      <canvas id="canvas" class="image-sequence"></canvas>
    </div>
  </div>
  <div class="section section1">
    <div class="column">
      <h1> De Zakkenspecialist </h1>
      <h1> voor elke branche de juiste zak </h1>
    </div>
  </div>
  <div class="section sectionMovie">
    <video class="videoLoop" width="100%" height="auto" loop muted autoplay>
      <source src="<?php echo get_stylesheet_directory_uri(); ?>/image-sequence/autoInpakken.mp4" type="video/mp4">
      Your browser does not support the video tag.
    </video>

    <div class="column">
      <h1> De Zakkenspecialist </h1>
      <h1> voor elke branche de juiste zak </h1>
    </div>
    <div class="column">
      <h1>Industry.</h1>
      <h1>Gemeente.</h1>
      <h1>Overheid.</h1>
      <h1>Fabriek.</h1>
      <h1>Horecea.</h1>
    </div>
  </div>
  <script>
    const canvas = document.getElementById("canvas");
    const ctx = canvas.getContext("2d");
    const frameCount = 155;
    const images = [];

    let currentFrame = 0;
    let loadedImages = 0;

    let currentFrameIndex = 0;
    let targetFrameIndex = 0;
    let lastFrame = -1;

    canvas.width = 1920;
    canvas.height = 934;

    for (let i = 1; i <= frameCount; i++) {
      const img = new Image();
      const imageNumber = ("00" + i).slice(-3);

      img.src = "<?php echo get_stylesheet_directory_uri(); ?>/image-sequence/images/" + imageNumber + ".webp"; // Update with actual path

      img.onload = () => {
          loadedImages++;
          if (loadedImages === frameCount) {
              drawFrame(0); // Initial draw once all images are ready
              animate();
          }
      };

      images.push(img);
    }

    // The higher this value the longer the scroll sequence takes to finish
    let maxScrollForAnimation = (window.innerHeight * 3);

    window.addEventListener("scroll", () => {
      // delen door 2 om dan 100vh te gebruiken om te de scroll te doen.
      const scrollFraction = Math.min(window.scrollY / maxScrollForAnimation, 1);

      targetFrameIndex = Math.min(frameCount - 1, Math.floor(scrollFraction * frameCount));
    });

    window.addEventListener("resize", () => {
      maxScrollForAnimation = window.innerHeight * 2 + 300;
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
  </script>
</body>
</html>
