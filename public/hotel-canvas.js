/**
 * hotel-canvas.js
 * Deepseek-style 2.5D Image Compositing and GSAP Animation
 */

let isAnimating = false;

// Pre-set transform origin for the dolly zoom directly onto the door
// The door center is exactly between --d-left (58%) and --d-right (77%)
gsap.set("#scene", { transformOrigin: "67.5% 50%" }); 

// Notice: user's image actually had the door on the RIGHT side.
// The clip paths in CSS are mapped to left: 58%, right: 77%.

window.startDoorAnimation = function(hotelId) {
    if(isAnimating) return;
    isAnimating = true;

    const masterCard = document.querySelector('.master-card');
    const resPanel = document.getElementById('reservation-panel');
    const iframe = document.getElementById('res-iframe');
    const nav = document.querySelector('.nav');

    // Attach iframe URL dynamically
    iframe.src = '/reservationhebergement/new?hebergement_id=' + hotelId;

    const tl = gsap.timeline();

    // 1. Hide UI smoothly
    if (masterCard) {
        tl.to(masterCard, { opacity: 0, x: 50, scale: 0.95, duration: 0.6, ease: "power2.inOut" }, 0);
    }
    if (nav) {
        tl.to(nav, { opacity: 0, y: -20, duration: 0.6, ease: "power2.inOut" }, 0);
    }

    // 2. Open the image door realistically inside the 3D perspective
    // Pushing the door open inward
    tl.to("#door-layer", { 
        rotateY: -105, 
        duration: 2.5, 
        ease: "power3.inOut" 
    }, 0.2);

    // Apply a shadow across the open door to give depth to the 2D image
    tl.to(".door-shadow", { opacity: 1, duration: 2.0 }, 0.2);

    // 3. The Cinematic Dolly Zoom
    // Fly through the hole ripped in the wall layer directly into the beach image layer
    tl.to("#scene", {
        scale: 5,        // zoom level
        duration: 3.5,
        ease: "power2.inOut"
    }, 0.8);

    // 4. Slide in your actual Symfony Backend Reservation form over the beautiful view!
    tl.to(resPanel, { 
        x: 0, 
        duration: 1.0, 
        ease: "power3.out" 
    }, 3.0);

    // Close Button Reverse Mapping
    document.getElementById('close-btn').onclick = () => {
        const rev = gsap.timeline();
        rev.to(resPanel, { x: '100%', duration: 0.6, ease: "power2.in" })
           .to("#scene", { scale: 1, duration: 2.5, ease: "power2.inOut" }, 0.4)
           .to(".door-shadow", { opacity: 0, duration: 2 }, 0.4)
           .to("#door-layer", { rotateY: 0, duration: 2, ease: "power3.inOut" }, 0.8)
           .to([masterCard, nav], { opacity: 1, x: 0, scale: 1, duration: 0.6, ease: "power2.out" }, 2.0)
           .eventCallback("onComplete", () => { isAnimating = false; });
    };
};
