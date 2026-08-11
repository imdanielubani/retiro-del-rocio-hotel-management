import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/work_order.dart';
import 'package:video_player/video_player.dart';

/// One attachment's thumbnail in the Work Order detail grid — a photo
/// preview, or a video-camera badge over a dark tile (fetching a real video
/// thumbnail needs a frame-extraction dependency this app doesn't carry yet).
class AttachmentThumbnail extends StatelessWidget {
  const AttachmentThumbnail({super.key, required this.attachment, required this.onTap});

  final WorkOrderAttachment attachment;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white.withValues(alpha: 0.04),
      borderRadius: BorderRadius.circular(12),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (attachment.isVideo)
              Container(
                color: Colors.black.withValues(alpha: 0.4),
                alignment: Alignment.center,
                child: const Icon(Icons.play_circle_fill_rounded, size: 36, color: Colors.white70),
              )
            else
              Image.network(
                attachment.url,
                fit: BoxFit.cover,
                loadingBuilder: (context, child, progress) => progress == null
                    ? child
                    : const Center(child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.gold)),
                errorBuilder: (_, _, _) => Container(
                  color: Colors.white.withValues(alpha: 0.06),
                  alignment: Alignment.center,
                  child: const Icon(Icons.broken_image_outlined, color: Colors.white38),
                ),
              ),
            if ((attachment.createdLabel ?? '').isNotEmpty)
              Positioned(
                left: 6,
                bottom: 6,
                right: 6,
                child: Text(
                  attachment.createdLabel!,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.style(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w600),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// Opens the attachment full-screen — a zoomable photo, or a playable video.
Future<void> showAttachmentViewer(BuildContext context, WorkOrderAttachment attachment) {
  return showDialog(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.9),
    builder: (_) => Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.all(16),
      child: Stack(
        alignment: Alignment.center,
        children: [
          attachment.isVideo ? _VideoView(url: attachment.url) : InteractiveViewer(child: Image.network(attachment.url)),
          Positioned(
            top: 0,
            right: 0,
            child: IconButton(
              icon: const Icon(Icons.close_rounded, color: Colors.white),
              onPressed: () => Navigator.of(context).pop(),
            ),
          ),
        ],
      ),
    ),
  );
}

class _VideoView extends StatefulWidget {
  const _VideoView({required this.url});

  final String url;

  @override
  State<_VideoView> createState() => _VideoViewState();
}

class _VideoViewState extends State<_VideoView> {
  late final VideoPlayerController _controller;
  bool _ready = false;

  @override
  void initState() {
    super.initState();
    _controller = VideoPlayerController.networkUrl(Uri.parse(widget.url))
      ..initialize().then((_) {
        if (mounted) setState(() => _ready = true);
        _controller.play();
      });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (!_ready) {
      return const SizedBox(
        width: 60,
        height: 60,
        child: CircularProgressIndicator(color: AppColors.gold),
      );
    }
    return AspectRatio(
      aspectRatio: _controller.value.aspectRatio,
      child: GestureDetector(
        onTap: () => setState(() => _controller.value.isPlaying ? _controller.pause() : _controller.play()),
        child: Stack(
          alignment: Alignment.center,
          children: [
            VideoPlayer(_controller),
            if (!_controller.value.isPlaying)
              const Icon(Icons.play_circle_fill_rounded, size: 56, color: Colors.white70),
          ],
        ),
      ),
    );
  }
}
