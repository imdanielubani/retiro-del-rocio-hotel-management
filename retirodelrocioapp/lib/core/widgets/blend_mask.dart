import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';

/// Composites its [child] onto everything painted behind it using [blendMode].
///
/// Used for additive imagery such as a gold glow layer whose black areas
/// should disappear (`BlendMode.screen`) rather than paint over the backdrop.
class BlendMask extends SingleChildRenderObjectWidget {
  const BlendMask({
    super.key,
    required this.blendMode,
    this.opacity = 1.0,
    super.child,
  });

  final BlendMode blendMode;
  final double opacity;

  @override
  RenderBlendMask createRenderObject(BuildContext context) =>
      RenderBlendMask(blendMode, opacity);

  @override
  void updateRenderObject(BuildContext context, RenderBlendMask renderObject) {
    renderObject
      ..blendMode = blendMode
      ..opacity = opacity;
  }
}

class RenderBlendMask extends RenderProxyBox {
  RenderBlendMask(this._blendMode, this._opacity);

  BlendMode _blendMode;
  double _opacity;

  set blendMode(BlendMode value) {
    if (_blendMode == value) return;
    _blendMode = value;
    markNeedsPaint();
  }

  set opacity(double value) {
    if (_opacity == value) return;
    _opacity = value;
    markNeedsPaint();
  }

  @override
  void paint(PaintingContext context, Offset offset) {
    context.canvas.saveLayer(
      offset & size,
      Paint()
        ..blendMode = _blendMode
        ..color = Color.fromRGBO(0, 0, 0, _opacity),
    );
    super.paint(context, offset);
    context.canvas.restore();
  }
}
